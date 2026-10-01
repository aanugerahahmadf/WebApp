<?php

namespace App\Http\Controllers\User\InvoicePdfController;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    public function download(Order $order, Request $request): Response
    {
        if ((int) auth()->id() !== (int) $order->user_id && ! auth()->user()?->hasRole('super_admin')) {
            abort(403);
        }

        $order->load(['user', 'package.category', 'package.media',
            'product.category', 'product.media',
            'latestTransaction']);

        $html = view('User.pdf.order-invoice.order-invoice', compact('order'))->render();

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'invoice-'.$order->order_number.'.pdf';
        $inline = $request->boolean('download') ? 'attachment' : 'inline';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$inline}; filename=\"{$filename}\"",
        ]);
    }
}
