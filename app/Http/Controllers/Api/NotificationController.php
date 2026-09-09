<?php
namespace App\Http\Controllers\Api;
use App\Traits\ApiResponseTrait; use Illuminate\Http\Request; use Illuminate\Support\Str;
class NotificationController { use ApiResponseTrait;
 public function index(Request $r){$n=$r->user()->notifications()->latest()->paginate(min((int)$r->get('per_page',15),100));return $this->success($n);}
 public function unread(Request $r){return $this->success($r->user()->unreadNotifications()->latest()->get());}
 public function markAsRead(Request $r,string $id){if(!Str::isUuid($id)){return $this->notFound();}$n=$r->user()->notifications()->findOrFail($id);$n->markAsRead();return $this->success($n,'Notification marked as read');}
 public function markAllRead(Request $r){$r->user()->unreadNotifications->each->markAsRead();return $this->success(null,'All notifications marked as read');}
 public function destroy(Request $r,string $id){if(!Str::isUuid($id)){return $this->notFound();}$r->user()->notifications()->whereKey($id)->firstOrFail()->delete();return $this->success(null,'Notification deleted');}
}
