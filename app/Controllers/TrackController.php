<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CaseService;

final class TrackController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private CaseService $cases)
    {
        parent::__construct($validator);
    }

    public function show(Request $request): Response
    {
        $number = strtoupper(trim((string) $request->query('number', '')));
        $result = $number !== '' ? $this->cases->track($number) : null;

        return $this->view('public/track', [
            'title' => __('track.title'),
            'description' => __('track.lead'),
            'active' => 'track',
            'robots' => 'noindex',
            'number' => $number,
            'result' => $result,
            'searched' => $number !== '',
        ]);
    }

    public function lookup(Request $request): Response
    {
        $data = $this->validate($request, ['number' => 'required|min:6|max:24']);

        return $this->redirect('/track?number=' . rawurlencode(strtoupper($data['number'])));
    }
}
