<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Book;
use App\Models\LoanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// HU-03 y HU-04. Contrato §3.4.
class BookController extends Controller
{
    // HU-04: búsqueda paginada, solo activos, orden por título.
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string'],
            'category_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = (int) ($data['page'] ?? 1);
        $size = (int) ($data['size'] ?? 20);

        $query = Book::with('category'); // SoftDeletes ya excluye los dados de baja
        if (! empty($data['q'])) {
            $q = '%'.$data['q'].'%';
            $query->where(fn ($w) => $w->where('title', 'ilike', $q)->orWhere('author', 'ilike', $q));
        }
        if (! empty($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }

        $total = (clone $query)->count(); // CA-04.2
        $items = $query->orderBy('title')->forPage($page, $size)->get();

        return ['items' => $items, 'page' => $page, 'size' => $size, 'total' => $total];
    }

    // CA-04.3: inexistente o dado de baja responde 404.
    public function show(string $id)
    {
        return $this->find($id)->load('category');
    }

    // CA-03.1, CA-03.2
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'isbn' => ['required', 'regex:/^\d{13}$/'],
            'category_id' => ['required', 'uuid', 'exists:category,id'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        // Único entre activos: la consulta excluye bajas lógicas. Repetido responde 409, no 422.
        if (Book::where('isbn', $data['isbn'])->exists()) {
            throw new BusinessException('ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.', 409);
        }

        $book = Book::create([
            'title' => trim($data['title']),
            'author' => trim($data['author']),
            'isbn' => $data['isbn'],
            'category_id' => $data['category_id'],
            'total_copies' => $data['total_copies'],
            'available_copies' => $data['total_copies'], // los disponibles arrancan iguales al total
            'cover_key' => null,
        ]);

        return response()->json($book->load('category'), 201);
    }

    // CA-03.3: reemplaza título, autor, categoría y total (el ISBN no cambia).
    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'uuid', 'exists:category,id'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        $book = DB::transaction(function () use ($data, $id) {
            $book = $this->find($id, lock: true);
            $available = $book->available_copies + ($data['total_copies'] - $book->total_copies);
            if ($available < 0) {
                throw new BusinessException('VALIDATION_ERROR', 'El nuevo total dejaría disponibles por debajo de cero.', 422,
                    [['field' => 'total_copies', 'issue' => 'menor que los ejemplares prestados']]);
            }
            $book->update([
                'title' => trim($data['title']),
                'author' => trim($data['author']),
                'category_id' => $data['category_id'],
                'total_copies' => $data['total_copies'],
                'available_copies' => $available,
            ]);
            return $book;
        });

        return $book->load('category');
    }

    // CA-03.5: baja lógica; con préstamos abiertos responde 409.
    public function destroy(string $id)
    {
        $book = $this->find($id);
        if (LoanItem::where('book_id', $book->id)->whereNull('returned_at')->exists()) {
            throw new BusinessException('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos sin devolver.', 409);
        }
        $book->delete();
        return response()->noContent();
    }
    private function find(string $id, bool $lock = false): Book
    {
        $book = null;
        if (Str::isUuid($id)) {
            $query = Book::query();
            if ($lock) {
                $query->lockForUpdate();
            }
            $book = $query->find($id);
        }
        if (! $book) {
            throw new BusinessException('NOT_FOUND', 'El libro no existe.', 404);
        }
        return $book;
    }
}
    