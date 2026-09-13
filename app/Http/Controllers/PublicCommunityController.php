<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PublicCommunityDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicCommunityController extends Controller
{
    public function __construct(private readonly PublicCommunityDirectory $directory) {}

    public function index(Request $request): View
    {
        $query = $request->string('q')->trim()->value();

        return view('public.pages.community', [
            'contributions' => $this->directory->search($query !== '' ? $query : null),
            'query' => $query,
        ]);
    }

    public function show(string $slug): View
    {
        $contribution = $this->directory->find($slug);
        abort_if($contribution === null, 404);

        return view('public.pages.community-show', ['contribution' => $contribution]);
    }
}
