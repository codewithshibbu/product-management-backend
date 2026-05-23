<?php

namespace App\Http\Controllers;

use App\Events\ProductLowStock;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $product->refresh();
        if ($product->is_low_stock) {
            event(new ProductLowStock($product));
        }

        return response()->json($product->load(['images', 'user']), 201);
    }
// get list of  product api (to be showwn in home page) 
    public function getList(Request $request)
    {
        $query = Product::with(['images', 'user']);

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

        $products = $query->orderBy($sort, $order)->paginate($rows);

        return response()->json(array_merge($products->toArray(), [
            'low_stock_count' => Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
        ]));
    }

    public function getProduct($product_id)
    {
        $product = Product::with(['images', 'user'])->find($product_id);

        if (! $product) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json($product);
    }

    public function updateProduct(Request $request, $product_id)
    {
        $product = Product::find($product_id);
        if (! $product) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer',
        ]);

        $product->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'stock_quantity' => $data['stock_quantity'],
            'low_stock_threshold' => $data['low_stock_threshold'],
        ]);

        if (! empty($data['remove_images'])) {
            $images = $product->images()->whereIn('id', $data['remove_images'])->get();
            foreach ($images as $img) {
                Storage::disk('public')->delete($img->path);
                $img->delete();
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store("products/{$product->id}", 'public');
                $product->images()->create(['path' => $path]);
            }
        }

        $product->refresh();
        if ($product->is_low_stock) {
            event(new ProductLowStock($product));
        }

        return response()->json($product->load(['images', 'user']));
    }

    public function deleteProduct($product_id)
    {
        $product = Product::with('images')->find($product_id);

        if (! $product) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $this->deleteProductRecord($product);


        return response()->json(['message' => 'Product deleted successfully']);
    }
// product list action to delete
    public function listAction(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,delete-all',
            'ids' => 'required_if:action,delete|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        if ($data['action'] === 'delete-all') {
            $products = Product::with('images')->get();
        } else {
            $products = Product::with('images')->whereIn('id', $data['ids'])->get();
        }

        $count = 0;
        foreach ($products as $product) {
            $this->deleteProductRecord($product);
            $count++;
        }

        return response()->json([
            'message' => "{$count} product(s) deleted successfully.",
            'deleted_count' => $count,
        ]);
    }

    private function deleteProductRecord(Product $product): void
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        $product->delete();
    }
}
