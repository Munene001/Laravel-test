<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * List orders with optional status and customer_id filters.
     */
    public function index(Request $request)
    {
        $orders = $this->orders();

        $status     = $request->query('status');
        $customerId = $request->query('customer_id');

        if ($customerId !== null && !ctype_digit((string) $customerId)) {
            return response()->json(['error' => 'Invalid customer_id parameter'], 422);
        }

        if ($status !== null) {
            $orders = array_filter($orders, fn($o) => ($o['status'] ?? null) === $status);
        }

        if ($customerId !== null) {
            $orders = array_filter($orders, fn($o) => (int)($o['customer']['id'] ?? 0) === (int) $customerId);
        }

        // array_values resets keys after array_filter so JSON renders as a list
        return response()->json(
            array_map([$this, 'withTotal'], array_values($orders))
        );
    }

    /**
     * Get a single order by ID.
     */
    public function show($id)
    {
        if (!ctype_digit((string) $id)) {
            return response()->json(['error' => 'Invalid order id'], 422);
        }

        foreach ($this->orders() as $order) {
            if ((int) $order['id'] === (int) $id) {
                return response()->json($this->withTotal($order));
            }
        }

        return response()->json(['error' => 'Order not found'], 404);
    }

    /**
     * Get all orders for a specific customer.
     */
    public function customerOrders($id)
    {
        if (!ctype_digit((string) $id)) {
            return response()->json(['error' => 'Invalid customer id'], 422);
        }

        $orders = array_filter(
            $this->orders(),
            fn($order) => (int)($order['customer']['id'] ?? 0) === (int) $id
        );

        return response()->json(
            array_map([$this, 'withTotal'], array_values($orders))
        );
    }

    /**
     * Read and decode orders.json from storage.
     */
    private function orders(): array
    {
        $path = 'orders.json';

        if (!Storage::exists($path)) {
            abort(404, 'File not found');
        }

        $orders = json_decode(Storage::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($orders)) {
            abort(500, 'Invalid orders data');
        }

        return $orders;
    }

    /**
     * Calculate and append order total: sum(quantity * unit_price).
     */
    private function withTotal(array $order): array
    {
        $order['total'] = array_reduce(
            $order['items'] ?? [],
            fn($sum, $item) => $sum + (($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0)),
            0
        );

        return $order;
    }
}
