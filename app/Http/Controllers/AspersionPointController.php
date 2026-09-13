<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAspersionPointRequest;
use App\Models\AspersionPoint;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AspersionPointController extends Controller
{
    public function store(StoreAspersionPointRequest $request): RedirectResponse
    {
        AspersionPoint::query()->create([
            ...$request->validated(),
            'public_token' => Str::random(48),
            'is_active' => true,
        ]);

        return back()->with('success', 'Ponto criado. O QR Code já está pronto para impressão.');
    }

    public function plate(Request $request, AspersionPoint $aspersionPoint): View
    {
        abort_unless($request->user()->role === 'master', 403);

        $publicUrl = route('aspersion.public.show', $aspersionPoint->public_token);
        $qrCode = new QrCode(
            data: $publicUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 560,
            margin: 24,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $qrCodeDataUri = (new SvgWriter)->write($qrCode)->getDataUri();

        return view('aspersion.plate', compact('aspersionPoint', 'publicUrl', 'qrCodeDataUri'));
    }
}
