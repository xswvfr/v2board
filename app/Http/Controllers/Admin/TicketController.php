<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index (Request $request) {
        if ($request->input('id')) {
            $ticket = Ticket::where('id', $request->input('id'))
                ->first();
            if (!$ticket) {
                abort(500, '工单不存在');
            }
            $ticket['message'] = TicketMessage::where('ticket_id', $ticket->id)->get();
            for ($i = 0; $i < count($ticket['message']); $i++) {
                if ($ticket['message'][$i]['user_id'] !== $ticket->user_id) {
                    $ticket['message'][$i]['is_me'] = true;
                } else {
                    $ticket['message'][$i]['is_me'] = false;
                }
            }
            return response([
                'data' => $ticket
            ]);
        }
        $ticket = Ticket::orderBy('created_at', 'DESC')
            ->get();
        for ($i = 0; $i < count($ticket); $i++) {
            if ($ticket[$i]['last_reply_user_id'] == $request->session()->get('id')) {
                $ticket[$i]['reply_status'] = 0;
            } else {
                $ticket[$i]['reply_status'] = 1;
            }
        }
        return response([
            'data' => $ticket
        ]);
    }

    public function reply (Request $request) {
        if (empty($request->input('id'))) {
            abort(500, '参数错误');
        }
        if (empty($request->input('message'))) {
            abort(500, '消息不能为空');
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->first();
        if (!$ticket) {
            abort(500, '工单不存在');
        }
        if ($ticket->status) {
            abort(500, '工单已关闭，无法回复');
        }
        DB::beginTransaction();
        $ticketMessage = TicketMessage::create([
            'user_id' => $request->session()->get('id'),
            'ticket_id' => $ticket->id,
            'message' => $request->input('message')
        ]);
        $ticket->last_reply_user_id = $request->session()->get('id');
        if (!$ticketMessage || !$ticket->save()) {
            DB::rollback();
            abort(500, '工单回复失败');
        }
        DB::commit();
        return response([
            'data' => true
        ]);
    }

    public function close (Request $request) {
        if (empty($request->input('id'))) {
            abort(500, '参数错误');
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->first();
        if (!$ticket) {
            abort(500, '工单不存在');
        }
        $ticket->status = 1;
        if (!$ticket->save()) {
            abort(500, '关闭失败');
        }
        return response([
            'data' => true
        ]);
    }
}
