<?php

namespace App\Http\Controllers;

use App\Settings\ContactSettings;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Contact Us page: admin-managed office details and a map preview. The
     * site deliberately has no public enquiry form.
     */
    public function index(): Response
    {
        return Inertia::render('contact', [
            'contact' => ContactSettings::resolve()->shared(),
        ]);
    }
}
