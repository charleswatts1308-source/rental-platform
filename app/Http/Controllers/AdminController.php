<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PageView;
use App\Models\ContactMessage;
use App\Mail\ContactReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    public function users()
    {
        $totalVerified = User::whereNotNull('email_verified_at')->count();
        $totalNonVerified = User::whereNull('email_verified_at')->count();

        // Monthly non-verified counts for last 3 months
        $monthlyNonVerified = [];
        for ($i = 0; $i < 3; $i++) {
            $date = now()->startOfMonth()->subMonths($i);
            $monthlyNonVerified[] = [
                'label' => $date->format('M y'),
                'count' => User::whereNull('email_verified_at')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count()
            ];
        }

        $users = User::whereNotNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $unverifiedUsers = User::whereNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return view('admin.users', compact('users', 'unverifiedUsers', 'totalVerified', 'totalNonVerified', 'monthlyNonVerified'));
    }

    public function pageViews()
    {
        // Common crawler/bot patterns
        $crawlerPatterns = [
            'Googlebot', 'Bingbot', 'YandexBot', 'Baiduspider', 'DuckDuckBot',
            'Slurp', 'facebot', 'ia_archiver', 'Applebot', 'AhrefsBot',
            'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot', 'Bytespider',
            'GPTBot', 'ClaudeBot', 'bot', 'crawler', 'spider'
        ];

        $totalPageViews = PageView::count();

        // Build crawler detection query
        $crawlerQuery = PageView::where(function ($query) use ($crawlerPatterns) {
            foreach ($crawlerPatterns as $pattern) {
                $query->orWhere('user_agent', 'LIKE', '%' . $pattern . '%');
            }
        });
        $crawlerPageViews = $crawlerQuery->count();
        $humanPageViews = $totalPageViews - $crawlerPageViews;

        $pageViews = PageView::orderBy('view_date_time', 'desc')
            ->limit(100)
            ->get();

        return view('admin.page-views', compact('pageViews', 'totalPageViews', 'crawlerPageViews', 'humanPageViews'));
    }

    public function contactMessages()
    {
        $messages = ContactMessage::with('user')
            ->orderByRaw('replied_at IS NOT NULL, created_at DESC')
            ->limit(100)
            ->get();

        return view('admin.contact-messages', compact('messages'));
    }

    public function contactMessageShow(ContactMessage $contactMessage)
    {
        $contactMessage->load('user');
        return view('admin.contact-message-show', compact('contactMessage'));
    }

    public function contactMessageReply(Request $request, ContactMessage $contactMessage)
    {
        if ($contactMessage->admin_reply) {
            return redirect()->route('admin.contact-messages.show', $contactMessage->id)
                ->with('success', 'This message has already been replied to.');
        }

        $request->validate([
            'admin_reply' => 'required|string|max:5000',
        ]);

        $contactMessage->update([
            'admin_reply' => $request->admin_reply,
            'replied_at' => now(),
        ]);

        Mail::to($contactMessage->user->email)->send(new ContactReply($contactMessage));

        return redirect()->route('admin.contact-messages.show', $contactMessage->id)
            ->with('success', 'Reply sent to ' . $contactMessage->user->email);
    }
}
