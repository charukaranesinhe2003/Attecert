<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\IntakeService;

final class QuoteController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private CatalogService $catalog,
        private IntakeService $intake
    ) {
        parent::__construct($validator);
    }

    public function form(): Response
    {
        return $this->view('public/quote', [
            'title' => __('quote.title'),
            'description' => content_block('quote_lead'),
            'active' => 'quote',
            'countries' => $this->catalog->countries(true),
            'services' => $this->catalog->services(true),
            'documentTypes' => $this->catalog->documentTypes(),
        ]);
    }

    public function submit(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'email' => 'required|email|max:190',
            'phone' => 'required|min:6|max:40',
            'whatsapp' => 'nullable|max:40',
            'country_id' => 'nullable|numeric',
            'document_type_id' => 'nullable|numeric',
            'document_count' => 'nullable|numeric|min:1|max:100',
            'message' => 'nullable|max:4000',
        ]);
        if (empty($request->input('consent'))) {
            throw new \App\Core\ValidationException(['consent' => __('validation.consent')], $request->all());
        }
        if ($customer = account_customer()) {
            $data['email'] = $customer['email'];
            $data['name'] = trim((string) $data['name']) !== '' ? $data['name'] : $customer['name'];
            $data['phone'] = trim((string) ($data['phone'] ?? '')) !== '' ? $data['phone'] : (string) $customer['phone'];
        }
        $serviceIds = array_map('intval', (array) $request->input('services', []));
        $result = $this->intake->quote($data, $serviceIds, $request->files('attachments'));
        $_SESSION['_track_popup'] = $result['number'];
        flash('success', __('quote.sent_with_code', ['number' => $result['number']]));

        return $this->redirect('/quote');
    }
}
