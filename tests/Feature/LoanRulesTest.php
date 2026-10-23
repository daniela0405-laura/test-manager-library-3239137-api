<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Evidencia de tasks.md T-09, T-10, T-11 y T-16: cada prueba nombra el criterio del spec que comprueba.
class LoanRulesTest extends TestCase
{
    use RefreshDatabase;

    private const NOVELA = '11111111-1111-4111-8111-111111111111';

    private int $seq = 0;

    private function actingAsRole(string $role): User
    {
        $user = User::create([
            'username' => $role.(++$this->seq),
            'password_hash' => bcrypt('secreto'),
            'role' => $role,
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function book(int $copies = 2, ?string $isbn = null): Book
    {
        $n = ++$this->seq;

        return Book::create([
            'title' => "Libro {$n}",
            'author' => 'Autor',
            'isbn' => $isbn ?? str_pad((string) $n, 13, '0', STR_PAD_LEFT),
            'category_id' => self::NOVELA,
            'total_copies' => $copies,
            'available_copies' => $copies,
            'cover_key' => null,
        ]);
    }

    private function member(): Member
    {
        $n = ++$this->seq;

        return Member::create(['full_name' => "Socio {$n}", 'document_number' => "DOC-{$n}"]);
    }

    private function lend(Member $member, array $books)
    {
        return $this->postJson('/api/v1/loans', [
            'member_id' => $member->id,
            'items' => array_map(fn ($b) => ['book_id' => $b->id], $books),
        ]);
    }

    public function test_ca_01_3_sin_token_401_y_rol_insuficiente_403(): void
    {
        $this->getJson('/api/v1/books')
            ->assertStatus(401)
            ->assertJsonStructure(['code', 'message', 'details']); // RNF-4

        $this->actingAsRole('librarian');
        $this->postJson('/api/v1/books', [])
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    public function test_ca_03_2_isbn_repetido_entre_activos_responde_409(): void
    {
        $this->actingAsRole('admin');
        $this->book(2, '9780307474728');

        $this->postJson('/api/v1/books', [
            'title' => 'Otro', 'author' => 'X', 'isbn' => '9780307474728',
            'category_id' => self::NOVELA, 'total_copies' => 1,
        ])->assertStatus(409)->assertJsonPath('code', 'ISBN_TAKEN');
    }

    public function test_ca_06_2_libro_repetido_en_el_prestamo_responde_422(): void
    {
        $this->actingAsRole('librarian');
        $b = $this->book();

        $this->lend($this->member(), [$b, $b])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_BOOK_IN_LOAN');
    }

    public function test_ca_06_3_sin_ejemplares_responde_409_y_no_registra_nada(): void
    {
        $this->actingAsRole('librarian');
        $ultimo = $this->book(1);
        $otro = $this->book(2);
        $this->lend($this->member(), [$ultimo])->assertCreated();

        $this->lend($this->member(), [$otro, $ultimo])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOK_NOT_AVAILABLE');

        $this->assertSame(0, $ultimo->fresh()->available_copies);
        $this->assertSame(2, $otro->fresh()->available_copies); // la transacción revirtió el descuento
        $this->assertDatabaseCount('loan', 1);
    }

    public function test_ca_06_4_maximo_tres_libros_sin_devolver(): void
    {
        $this->actingAsRole('librarian');
        $socio = $this->member();
        $this->lend($socio, [$this->book(), $this->book(), $this->book()])->assertCreated();

        $this->lend($socio, [$this->book()])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MEMBER_LIMIT_EXCEEDED');
    }

    public function test_ca_06_5_socio_con_vencidos_no_pide_y_estado_overdue(): void
    {
        $this->actingAsRole('librarian');
        $socio = $this->member();

        $this->travel(-20)->days(); // préstamo de hace 20 días: venció a los 14
        $loan = $this->lend($socio, [$this->book()])->assertCreated();
        $this->travelBack();

        $this->getJson('/api/v1/loans/'.$loan->json('id'))->assertJsonPath('status', 'overdue');
        $this->getJson('/api/v1/loans/overdue')->assertJsonCount(1, 'items');

        $this->lend($socio, [$this->book()])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEMBER_HAS_OVERDUE');
    }

    public function test_ca_07_2_devolver_dos_veces_no_suma_dos_veces(): void
    {
        $this->actingAsRole('librarian');
        $b = $this->book(1);
        $loan = $this->lend($this->member(), [$b])->assertCreated();
        $url = '/api/v1/loans/'.$loan->json('id').'/items/'.$loan->json('items.0.id').'/return';

        $this->postJson($url)->assertOk();
        $this->postJson($url)
            ->assertStatus(409)
            ->assertJsonPath('code', 'ITEM_ALREADY_RETURNED');

        $this->assertSame(1, $b->fresh()->available_copies);
        $this->getJson('/api/v1/loans/'.$loan->json('id'))->assertJsonPath('status', 'closed');
    }

    public function test_ca_10_4_reporte_conserva_el_titulo_congelado(): void
    {
        $this->actingAsRole('admin');
        $b = $this->book();
        $original = $b->title;
        $this->lend($this->member(), [$b])->assertCreated();

        $b->update(['title' => 'Titulo nuevo']); // se renombra después del préstamo

        $hoy = now()->toDateString();
        $this->getJson("/api/v1/reports/most-borrowed?from={$hoy}&to={$hoy}")
            ->assertOk()
            ->assertJsonPath('rows.0.book_title', $original)
            ->assertJsonPath('rows.0.times_borrowed', 1);
    }
}