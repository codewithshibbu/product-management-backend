<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    

    public function createProduct(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $product = Product::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'stock_quantity' => $data['stock_quantity'],
            'low_stock_threshold' => $data['low_stock_threshold'],
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store("products/{$product->id}", 'public');
                $product->images()->create([
                    'path' => $path,
                    'sort_order' => $i,
                ]);
            }
        }

        return response()->json($product->load('images'), 201);
    }
// get list of  product api (to be showwn in home page) 
    public function getList(Request $request)
    {
        $query = Product::with('images');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->query('max_price'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }

        $sort = $request->query('sort', 'created_at');
        $order = $request->query('order', 'desc');
        $allowedSort = ['name', 'price', 'stock_quantity', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        $rows = (int) $request->query('rows', 10);
        if ($rows < 1) {
            $rows = 10;
        }

        return $query->orderBy($sort, $order)->paginate($rows);
    }
}
