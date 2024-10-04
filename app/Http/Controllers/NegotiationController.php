<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Negotiation;
use App\Models\Reply;
use App\Models\RentalAgreement;
use App\Models\BillingDetail;
use App\Models\Notification;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class NegotiationController extends Controller
{
    /**
     * Show the list of negotiations for the authenticated user (either space owner or business owner).
     */
    public function index()
    {
        // Fetch negotiations where the current user is either the sender (business owner) or receiver (space owner)
        $negotiations = Negotiation::where('senderID', Auth::id())
                                   ->orWhere('receiverID', Auth::id())
                                   ->with('listing', 'sender', 'receiver')
                                   ->get();

        // Check role and return appropriate view
        if (Auth::user()->role === 'business_owner') {
            return view('business_owner.negotiations', compact('negotiations'));
        } else {
            return view('space_owner.negotiations', compact('negotiations'));
        }
    }

    /**
     * Show the negotiation details with messages.
     */
    public function show($negotiationID)
    {
        $negotiation = Negotiation::with('listing', 'sender', 'receiver', 'replies')->findOrFail($negotiationID);

        // Conditionally return the correct view based on the user's role
        if (Auth::user()->role === 'business_owner') {
            return view('business_owner.messagedetail', compact('negotiation'));
        } else if (Auth::user()->role === 'space_owner') {
            return view('space_owner.messagedetail', compact('negotiation'));
        }
    }
    public function updateOfferAmount(Request $request, $id)
    {
    $request->validate([
        'offerAmount' => 'required|numeric|min:0',
    ]);

    $negotiation = Negotiation::findOrFail($id);
    $negotiation->offerAmount = $request->input('offerAmount');
    $negotiation->save();

    return redirect()->back()->with('success', 'Offer amount updated successfully.');
    }

    /**
     * Store a reply (message) for a negotiation.
     */
    public function reply(Request $request, $negotiationID)
    {
    $request->validate([
        'message' => 'nullable|string|max:1000',
        'aImage' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Adjust the rules as needed
    ]);

    $negotiation = Negotiation::findOrFail($negotiationID);

    // Ensure only the sender (business owner) or receiver (space owner) can reply
    if (Auth::id() != $negotiation->senderID && Auth::id() != $negotiation->receiverID) {
        abort(403, 'Unauthorized');
    }

    // Handle the image upload
    $imageName = null;
    if ($request->hasFile('aImage')) {
        $image = $request->file('aImage');
        $imageName = $image->getClientOriginalName(); // Get the original name of the uploaded file
        $image->storeAs('negotiation_images', $imageName, 'public'); // Store the file with its original name
    }

    // Prepare the reply data
    $replyData = [
        'negotiationID' => $negotiationID,
        'senderID' => Auth::id(),
        'message' => $imageName ?? $request->input('message'), // Save the image name or the message text
    ];

    // Create a reply
    Reply::create($replyData);

    // Conditionally redirect based on the user's role
    return redirect()->route('negotiation.show', ['negotiationID' => $negotiationID]);
    }

    /**
     * Get all messages for a negotiation (for API or dynamic loading purposes).
     */
    public function getMessages($negotiationID)
    {
        $replies = Reply::where('negotiationID', $negotiationID)->get();
        return response()->json($replies);
    }
    public function store(Request $request)
    {
        $request->validate([
            'listingID' => 'required|exists:listing,listingID',
            'receiverID' => 'required|exists:users,userID',
            'message' => 'required|string',
            'offerAmount' => 'required|numeric',
        ]);

        $negotiation = Negotiation::create([
            'listingID' => $request->listingID,
            'senderID' => Auth::id(),
            'receiverID' => $request->receiverID,
            'message' => $request->message, 
            'negoStatus' => 'Pending',
            'offerAmount' => $request->offerAmount,
        ]);

        Notification::create([
            'n_userID' => $request->receiverID,  // Notify the space owner (receiver)
            'type' => 'negotiation',  // You can define this type for negotiation
            'data' => $negotiation->listing->title, // Custom message
            'created_at' => now(),
        ]);

        return redirect()->route('business.negotiations')->with('success', 'Your offer has been sent successfully, and the space owner has been notified.');
    }
    public function storeDB(Request $request, $negotiationID)
    {
    // Validate the incoming request data
    $validatedData = $request->validate([
        'gcashNumber' => 'required|unique:billing_details,gcash_number|max:255',
        'myCheckbox' => 'required'
    ]);

    // Create a new billing detail
    BillingDetail::create([
        'user_id' => Auth::id(),
        'rental_agreement_id' => $negotiationID,  
        'gcash_number' => $validatedData['gcashNumber'],
    ]);

    // Redirect or send back a response after successful creation
    return redirect()->route('space.business_details')->with('success', 'Billing details have been saved successfully.');
    }

    public function updateStatus(Request $request, $negotiationID)
    {
    // Validate the status field
    $request->validate([
        'status' => 'required|in:Pending,Approved,Disapproved',
    ]);

    // Find the negotiation by ID
    $negotiation = Negotiation::findOrFail($negotiationID);

    // Ensure the current user is either the receiver (space owner) or the sender (business owner)
    if (Auth::id() != $negotiation->receiverID && !Auth::user()->hasRole('business_owner')) {
        abort(403, 'Unauthorized');
    }

    // Update the status
    $negotiation->negoStatus = $request->input('status');
    $negotiation->save();

    // Notify the Business Owner
    $this->notifyBusinessOwner($negotiation, $request->input('status'));

    // Redirect back with a success message
    return redirect()->route('negotiation.show', ['negotiationID' => $negotiationID])
                    ->with('success', 'Negotiation status updated successfully.');
    }

    public function rentAgree(Request $request, $negotiationID)
    {

    // Validate the input fields
    $request->validate([
        'ownerID' => 'required|exists:users,userID',
        'renterID' => 'required|exists:users,userID',
        'listingID' => 'required|exists:listing,listingID',
        'rentalTerm' => 'required|in:weekly,monthly,yearly',
        'offerAmount' => 'required|numeric',
        'startDate' => 'required|date',
        'endDate' => 'required|date|after_or_equal:startDate', // Ensure end date is after start date
    ]);

    // Create a new rental agreement and insert into the database
    RentalAgreement::create([
        'ownerID' => $request->input('ownerID'),
        'renterID' => $request->input('renterID'),
        'listingID' => $request->input('listingID'),
        'rentalTerm' => $request->input('rentalTerm'),
        'dateCreated' => now(),
        'offerAmount' => $request->input('offerAmount'),
        'dateStart' => $request->input('startDate'),
        'dateEnd' => $request->input('endDate'),
        'status' => 'Agree', // Set status to 'Agree' by default
    ]);

    // Redirect to the business owner dashboard after successful insert
    return redirect()->route('business.dashboard')->with('success', 'Rental agreement created successfully.');
    }
    public function showPaymentDetails(Request $request)
    {
    // Fetch negotiations where the authenticated user is involved (either as sender or receiver)
    $negotiations = Negotiation::where('senderID', Auth::id())
                                ->orWhere('receiverID', Auth::id())
                                ->with('listing', 'sender', 'receiver','bill')
                                ->get();
    
    // Assuming the sender is the Business Owner
    $businessOwner = $negotiations->first()->sender; // You can adjust based on your role logic
    $listing = $negotiations->first()->listing;
    $billingDetails = BillingDetail::where('user_id', Auth::id())->first();

    return view('space_owner.payment_details', compact('negotiations', 'businessOwner','listing','billingDetails'));
    }
    protected function notifyBusinessOwner($negotiation, $newStatus)
    {
        // Find the business owner (sender of the negotiation)
        $businessOwner = $negotiation->sender;

        // Check if the business owner exists
        if ($businessOwner) {
            // Create a notification for the business owner
            Notification::create([
                'n_userID' => $businessOwner->userID,  // The business owner's user ID
                'data' => 'Your negotiation for "' . $negotiation->listing->title . '" has been ' . $newStatus . '.',  // Custom message
                'type' => 'negotiation_status_update',  // Define the type of notification
            ]);
        }
    }
}