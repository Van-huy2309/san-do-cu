<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use App\Services\EscrowService;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    public function store(Request $request, Order $order, EscrowService $escrow, MediaService $media)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->canDispute(), 403, 'Đơn này không thể khiếu nại.');

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Dispute::REASONS))],
            'detail' => 'required|string|min:10|max:2000',
            'evidence' => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,pdf|max:12288',
        ]);

        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $media->storePublic($request->file('evidence'), 'disputes');
        }

        try {
            $escrow->openDispute($order, $request->user(), $data);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã mở khiếu nại. Relic giữ tiền escrow đến khi xử lý xong.');
    }
}
