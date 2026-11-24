<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminContentRequest;
use App\Services\Admin\AdminContentInventory;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentController extends Controller
{
    public function __construct(private readonly AdminContentInventory $inventory) {}

    public function links(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'links');
    }

    public function bioLinks(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'biolinks');
    }

    public function qrCodes(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'qr-codes');
    }

    public function folders(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'folders');
    }

    public function domains(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'custom-domains');
    }

    public function overlays(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'link-overlays');
    }

    public function pages(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'link-pages');
    }

    public function pixels(AdminContentRequest $request): Response
    {
        return $this->inventoryPage($request, 'pixels');
    }

    private function inventoryPage(AdminContentRequest $request, string $key): Response
    {
        return Inertia::render('admin/content/index', $this->inventory->props($request, $key));
    }
}
