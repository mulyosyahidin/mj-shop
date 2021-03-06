<?php

namespace App\Http\Controllers\Admin;

use App\Constants;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\Order_shipping;
use App\Models\Shipping_courier;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders = Order::with(['status', 'user'])->latest()->paginate();

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        $shippingCouriers = Shipping_courier::all();

        return view('admin.orders.show', compact('order', 'shippingCouriers'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        $action = $request->action;

        switch ($action) {
            case 'cancel-order':
                $order->status_id = Constants::ORDER_STATUS_CANCELLED;
                $order->save();

                $cancellations = [
                    'user_id' => auth()->user()->id,
                    'reason' => $request->reason
                ];

                $order->cancellations()->create($cancellations);

                return redirect()
                    ->back()
                    ->withSuccess('Berhasil membatalkan order');
                break;
            case 'insert-resi-number':
                $request->validate([
                    'courier_id' => 'required|numeric',
                    'resi_number' => 'required|min:4',
                    'notes' => 'nullable|min:4',
                    'picture' => 'required|max:5096|mimes:jpg,jpeg,png'
                ]);

                $order->status_id = Constants::ORDER_STATUS_ON_DELIVERY;
                $order->save();

                $shipping = new Order_shipping();
                $shipping->order_id = $order->id;
                $shipping->courier_id = $request->courier_id;
                $shipping->resi_number = $request->resi_number;
                $shipping->notes = $request->notes;
                $shipping->save();

                if ($request->has('picture') && $request->file('picture')->isValid()) {
                    $shipping->addMediaFromRequest('picture')
                        ->toMediaCollection('resi_number_pictures');
                }

                return redirect()
                    ->back()
                    ->withSuccess('Berhasil menginput data nomor resi');
                break;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        //
    }

    public function status($status = '')
    {
        $statuses = ['unpaid', 'on-process', 'on-delivery', 'finished', 'cancelled'];

        if (!in_array($status, $statuses)) {
            abort(404);
        }

        switch ($status) {
            case 'unpaid':
                $statusId = Constants::ORDER_STATUS_UNPAID;
                $statusText = 'Order Belum Dibayar';
                break;
            case 'on-process':
                $statusId = Constants::ORDER_STATUS_ON_PROCESS;
                $statusText = 'Order Sedang Diproses';
                break;
            case 'on-delivery':
                $statusId = Constants::ORDER_STATUS_ON_DELIVERY;
                $statusText = 'Order Dalam Pengiriman';
                break;
            case 'finished':
                $statusId = Constants::ORDER_STATUS_FINISHED;
                $statusText = 'Order Selesai';
                break;
            case 'cancelled':
                $statusId = Constants::ORDER_STATUS_CANCELLED;
                $statusText = 'Order Dibatalkan';
                break;
        }
        $orders = Order::where('status_id', $statusId)->with('user')->latest()->paginate();

        return view('admin.orders.status', compact('orders', 'statusText'));
    }
}
