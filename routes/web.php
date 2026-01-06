<?php

use App\Constants\UserType;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\WeekmenuController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmailContentController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ClientHomeController;
use App\Http\Controllers\ClientOrderController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\UserMigrateController;

Route::get('/images/menu-{id}.jpg', function ($id) {
    $menu = \App\Models\Menu::find($id);
    
    if (!$menu || !$menu->has_image) {
        abort(404);
    }
    
    return response()->file($menu->getImagePath());
})->name('menu.image');

// Client Registration Migration Route from mama-liu.com
Route::get('/auth/migrate/{token}', [UserMigrateController::class, 'migrate'])->name('auth.migrate');

// Master (Admin) Routes
Route::middleware(['auth', 'usertype:' . UserType::ADMIN])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::post('orders', [OrderController::class, 'store'])->name('admin.orders_store');
    Route::post('orders/change-client/{user}', [OrderController::class, 'changeClient'])->name('admin.orders_change_client');
    Route::put('orders/{order}', [OrderController::class, 'update'])->name('admin.orders_update');
    Route::get('orders', [OrderController::class, 'index'])->name('admin.orders_index');
    Route::get('orders/export', [OrderController::class, 'export'])->name('admin.orders_export');
    Route::get('orders/pdf', [OrderController::class, 'pdf'])->name('admin.orders_pdf');

    Route::post('weekmenus/reorder', [WeekmenuController::class, 'reorder'])->name('admin.weekmenus_reorder');
    Route::post('weekmenus/close-order', [WeekmenuController::class, 'closeOrder'])->name('admin.weekmenus_close_order');
    Route::post('weekmenus/toggle-invitation', [WeekmenuController::class, 'toggleInvitation'])->name('admin.weekmenus_toggle_invitation');
    Route::post('weekmenus', [WeekmenuController::class, 'store'])->name('admin.weekmenus_store');
    Route::put('weekmenus/{weekmenu}', [WeekmenuController::class, 'update'])->name('admin.weekmenus_update');
    Route::patch('weekmenus/{weekmenu}/quantity', [WeekmenuController::class, 'updateQuantity'])->name('admin.weekmenus_update_quantity');
    Route::delete('weekmenus/{weekmenu}', [WeekmenuController::class, 'destroy'])->name('admin.weekmenus_destroy');
    Route::get('weekmenus/list', [WeekmenuController::class, 'getWeekmenus'])->name('weekmenus_list');
    Route::get('weekmenus', [WeekmenuController::class, 'index'])->name('admin.weekmenus_index');
    Route::get('weekmenus/{weekmenu}', [WeekmenuController::class, 'show'])->name('admin.weekmenus_show');

    Route::post('menus', [MenuController::class, 'store'])->name('admin.menus.store');
    Route::put('menus/{menu}', [MenuController::class, 'update'])->name('admin.menus.update');
    Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('admin.menus.destroy');
    Route::get('menus', [MenuController::class, 'index'])->name('admin.menus_index');
    Route::get('menus/{menu}', [MenuController::class, 'show'])->name('admin.menus_show');

    Route::post('clients', [ClientController::class, 'store'])->name('admin.clients_store');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('admin.clients_update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('admin.clients_destroy');
    Route::get('clients/list', [ClientController::class, 'getClients'])->name('admin.clients_list');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('admin.clients_show');
    Route::get('clients', [ClientController::class, 'index'])->name('admin.clients_index');
    
    Route::get('invites', [InviteController::class, 'index'])->name('admin.invites_index');
    Route::post('invites', [InviteController::class, 'store'])->name('admin.invites_store');
    Route::delete('invites/{token}', [InviteController::class, 'destroy'])->name('admin.invites_destroy');

    Route::post('groups', [GroupController::class, 'store'])->name('admin.groups_store');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('admin.groups_update');
    Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('admin.groups_destroy');
    Route::get('groups', [GroupController::class, 'index'])->name('admin.groups_index');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('admin.groups_show');

    Route::get('emails', [EmailContentController::class, 'index'])->name('admin.emails_index');
    Route::get('emails/{email}', [EmailContentController::class, 'show'])->name('admin.emails_show');

    Route::get('invoices/by-user/{user}', [InvoiceController::class, 'getByUser'])->name('admin.invoices_by_user');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('admin.invoices_store');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('admin.invoices_pdf');
});

// Update the client routes section:
Route::middleware(['auth', 'usertype:' . UserType::CLIENT])->group(function () {
    Route::get('/', [ClientHomeController::class, 'index'])->name('home');
    Route::post('/place-order', [ClientHomeController::class, 'placeOrder'])->name('place_order');
    Route::get('/orders', [ClientOrderController::class, 'index'])->name('user.orders_index');
});

require __DIR__.'/settings.php';