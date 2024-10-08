<?php

use App\Http\Controllers\NegotiationController;
use App\Http\Controllers\OtpVerifyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpaceOwnerController;
use App\Http\Controllers\BusinessOwnerController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CreateListingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Auth\EmailVerifyController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('register/space', [RegisteredUserController::class, 'createSpaceOwner'])->name('space.register');
Route::post('register/space', [RegisteredUserController::class, 'storeSpaceOwner'])->name('space.register.post');

Route::get('register/business', [RegisteredUserController::class, 'createBusinessOwner'])->name('business.register');
Route::post('register/business', [RegisteredUserController::class, 'storeBusinessOwner'])->name('business.register.post');

// Redirect to the appropriate dashboard based on the user's role
Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->role === 'space_owner') {
        return redirect()->route('space.dashboard');
    } elseif ($user->role === 'business_owner') {
        return redirect()->route('business.dashboard');
    } elseif ($user->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
     else {
        abort(403, 'Unauthorized');
    }
})->middleware(['auth', 'verified'])->name('dashboard');

// Space Owner Dashboard Route
Route::get('/space/dashboard', [SpaceOwnerController::class, 'index'])
    ->name('space.dashboard')
    ->middleware(['auth', 'verified', 'role:space_owner']);

// Business Owner Dashboard Route
Route::get('/business/dashboard', [BusinessOwnerController::class, 'index'])
    ->name('business.dashboard')
    ->middleware(['auth', 'verified', 'role:business_owner']);

// Admin Dashboard Route
Route::get('/admin/dashboard', [AdminController::class, 'index'])
    ->name('admin.dashboard')
    ->middleware(['auth', 'verified', 'role:admin']);

// Admin Navbars 
Route::get('/admin/usermanagement', [AdminController::class, 'users'])
    ->name('admin.usermanagement')
    ->middleware(['auth', 'verified', 'role:admin']);

Route::get('/admin/usermanagement/space-owners', [AdminController::class, 'spaceOwners'])
    ->name('admin.spaceOwners')
    ->middleware(['auth', 'verified', 'role:admin']);

Route::get('/admin/usermanagement/business-owners', [AdminController::class, 'businessOwners'])
    ->name('admin.businessOwners')
    ->middleware(['auth', 'verified', 'role:admin']);

Route::get('/admin/usermanagement/admins', [AdminController::class, 'adminUsers'])
    ->name('admin.adminUsers')
    ->middleware(['auth', 'verified', 'role:admin']);
// ADD NEW ADMIN
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin/usermanagement/admins/add', [AdminController::class, 'create'])->name('admin.create');
    Route::post('/admin/usermanagement/admins', [AdminController::class, 'store'])->name('admin.store');
});


// Admin LISTINGS
Route::get('/admin/listingmanagement', [AdminController::class, 'listing'])
    ->name('admin.listingmanagement')
    ->middleware(['auth', 'verified', 'role:admin']);

Route::post('/admin/listingmanagement/approve-listing/{listingID}', [AdminController::class, 'approveListing'])->name('admin.approveListing');
Route::post('/admin/listingmanagement/disapprove-listing/{listingID}', [AdminController::class, 'disapproveListing'])->name('admin.disapproveListing');

Route::get('/admin/listingmanagement/view/{listingID}', [AdminController::class, 'viewListing'])->name('admin.viewListing');



Route::get('/admin/payment', [AdminController::class, 'payment'])
    ->name('admin.payment')
    ->middleware(['auth', 'verified', 'role:admin']);

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
        Route::get('/admin/notifications/create', [NotificationController::class, 'create'])->name('admin.notifications.create');
        Route::post('/admin/notifications', [NotificationController::class, 'store'])->name('admin.notifications.store');
});

// display modal places/location
Route::get('/business/place/{location}', [BusinessOwnerController::class, 'showByLocation'])
    ->name('place.showByLocation');

// View details for a specific listing/spaces
Route::get('/business/place/detail/{listingID}', [BusinessOwnerController::class, 'detail'])
    ->name('place.detail');

//Space Owner Navbars
Route::get('/space/newspaces', [SpaceOwnerController::class, 'newspaces'])
    ->name('space.newspaces')
    ->middleware(['auth', 'verified', 'role:space_owner']);

//create new listing
Route::middleware('auth')->group(function () {
    Route::post('/space/dashboard', [CreateListingController::class, 'store'])->name('space.new.store');
});    
//edit and update listing
Route::get('/spaces/{listingID}/edit', [SpaceOwnerController::class, 'edit'])->name('space_owner.edit');
Route::post('/spaces/{listingID}/edit', [SpaceOwnerController::class, 'update'])->name('space_owner.update');
Route::post('/spaces/listings/{listingID}', [SpaceOwnerController::class, 'destroy'])->name('listings.destroy');
Route::post('spaces/listings/{listingID}/restore', [SpaceOwnerController::class, 'restore'])->name('listings.restore');


//delete image and edit
Route::delete('/spaces/image/{listingImageID}', [SpaceOwnerController::class, 'deleteImage'])->name('space_owner.delete_image');
Route::post('/spaces/{listingID}/add_image', [SpaceOwnerController::class, 'addImage'])->name('space_owner.add_image');

// SPACE NEGOTIATIONS
Route::get('/space/negotiations', [App\Http\Controllers\NegotiationController::class, 'index'])->name('space.negotiations');

Route::get('space/negotiations/{negotiationID}', [App\Http\Controllers\NegotiationController::class, 'show'])->name('negotiation.show');

// SPACE FEEDBACK
Route::get('/space/reviews', [SpaceOwnerController::class, 'reviews'])
    ->name('space.reviews')
    ->middleware(['auth', 'verified', 'role:space_owner']);

Route::post('/space/reviews.submit', [SpaceOwnerController::class, 'submiit'])->name('space.submit');

Route::post('space/negotiations/store', [App\Http\Controllers\NegotiationController::class, 'store'])->name('negotiation.store');

// SPACE OWNER NEGOTIATIONS
// For fetching messages
Route::get('/negotiations/{negotiationID}/reply', [App\Http\Controllers\NegotiationController::class, 'getMessages'])->name('negotiation.getMessages');

// For posting replies
Route::post('space/negotiations/{negotiationID}/reply', [App\Http\Controllers\NegotiationController::class, 'reply'])->name('negotiation.reply');


// space owner update status
Route::post('space/negotiations/{negotiationID}/status', [App\Http\Controllers\NegotiationController::class, 'updateStatus'])->name('negotiation.updateStatus');

Route::post('business/negotiations/{negotiationID}/billingStore', [NegotiationController::class, 'storeDB'])->name('billing.store');

Route::get('/space/payment', [NegotiationController::class, 'showPaymentDetails'])
    ->name('space.business_details')
    ->middleware(['auth', 'verified', 'role:space_owner']);


//business owner agreement
Route::post('business/negotiations/{negotiationID}/rent-agreement', [App\Http\Controllers\NegotiationController::class, 'rentAgree'])->name('negotiation.rentAgree');


// BUSINESS OWNER NEGOTIATIONS
Route::get('/business/negotiations', [App\Http\Controllers\NegotiationController::class, 'index'])->name('business.negotiations');

Route::get('business/negotiations/{negotiationID}', [App\Http\Controllers\NegotiationController::class, 'show'])->name('negotiation.show');

Route::put('/business/negotiations/{negotiationID}/updateOfferAmount', [NegotiationController::class, 'updateOfferAmount'])->name('business.updateOfferAmount')->middleware(['auth', 'verified', 'role:business_owner']);


Route::post('business/negotiations/{negotiationID}/reply', [App\Http\Controllers\NegotiationController::class, 'reply'])->name('negotiation.reply');

Route::get('/business/negotiations/payment/{negotiationID}', [BusinessOwnerController::class, 'proceedToPayment'])->name('business.proceedToPayment');
Route::post('/business/negotiations/payment/{negotiationID}', [BusinessOwnerController::class, 'storeProofOfPayment'])->name('businessOwner.storeProofOfPayment');



Route::get('/business/bookinghistory', [BusinessOwnerController::class, 'bookinghistory'])
    ->name('business.bookinghistory')
    ->middleware(['auth', 'verified', 'role:business_owner']);

Route::get('/business/feedback', [BusinessOwnerController::class, 'feedback'])
    ->name('business.feedback')
    ->middleware(['auth', 'verified', 'role:business_owner']);

Route::get('/business/feedback/{rentalAgreementID}', [BusinessOwnerController::class, 'action'])
    ->name('business.action')
    ->middleware(['auth', 'verified', 'role:business_owner']);

Route::post('/business/feedback/submit', [BusinessOwnerController::class, 'submit'])
    ->name('business.submit');

//NOTIFICATIONS 
Route::get('/notifications', [NotificationController::class, 'getNotifications'])->name('notifications.all');

Route::get('/space/notifications/unread', [NotificationController::class, 'getUnreadNotifications'])->name('notifications.unread');

Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

// SEND EMAIL FOR VERIFICATION
Route::get('verify-otp', function () {
    return view('emails.verify_email_otp'); 
})->name('otp.verify');

Route::post('send-email-verification', [OtpVerifyController::class, 'store'])->name('email.send');
Route::post('otp-verify', [OtpVerifyController::class, 'verifyOtp'])->name('otp.verify.submit');

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__.'/auth.php';
