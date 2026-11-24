<?php

namespace App\Http\Middleware;

use App\Enums\PlatformPermission;
use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\QrCode;
use App\Models\TrackingPixel;
use App\Services\Platform\AdminContentMutation;
use App\Services\Platform\PlatformAuthorization;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopedAdminContent
{
    public const ATTRIBUTE = 'admin_content_scope';

    private const RESOURCES = [
        'link' => [Link::class, PlatformPermission::AdminLinksManage, '/admin/links'],
        'bioPage' => [BioPage::class, PlatformPermission::AdminBioPagesManage, '/admin/biolinks'],
        'qrCode' => [QrCode::class, PlatformPermission::AdminQrCodesManage, '/admin/qr-codes'],
        'folder' => [Folder::class, PlatformPermission::AdminFoldersManage, '/admin/folders'],
        'customDomain' => [CustomDomain::class, PlatformPermission::AdminDomainsManage, '/admin/custom-domains'],
        'linkOverlay' => [LinkOverlay::class, PlatformPermission::AdminOverlaysManage, '/admin/link-overlays'],
        'linkPage' => [LinkPage::class, PlatformPermission::AdminLinkPagesManage, '/admin/link-pages'],
        'trackingPixel' => [TrackingPixel::class, PlatformPermission::AdminPixelsManage, '/admin/pixels'],
    ];

    public function __construct(
        private readonly PlatformAuthorization $authorization,
        private readonly AdminContentMutation $mutations,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $matches = [];
        foreach (self::RESOURCES as $parameter => [$class, $permission, $listUrl]) {
            $root = $request->route($parameter);
            if ($root instanceof $class) {
                $matches[] = [$root, $permission, $listUrl];
            }
        }
        abort_unless(count($matches) === 1, 404);
        [$root, $permission, $listUrl] = $matches[0];
        $workspace = $root->workspace()->first();
        abort_if($workspace === null, 404);
        abort_unless($this->authorization->allows($request->user(), $permission), 403);

        $scope = [
            'root' => $root,
            'workspace' => $workspace,
            'actor_id' => $request->user()->getKey(),
            'permission' => $permission,
            'list_url' => $listUrl,
            'editor_url' => $listUrl.'/'.$root->getRouteKey(),
        ];
        $request->attributes->set(self::ATTRIBUTE, $scope);

        if ($request->isMethodSafe() || $request->routeIs('admin.content.domains.verify')) {
            return $next($request);
        }

        $response = $this->mutations->run($request, $root, (string) $request->route()->getName(), fn () => $next($request));
        if ($response instanceof RedirectResponse && $response->exception === null && $response->getStatusCode() < 400) {
            $keepEditor = $root instanceof BioPage || $root instanceof LinkPage
                || $request->routeIs('admin.content.domains.reissue', 'admin.content.pixels.rules.*');
            $destination = $keepEditor ? $scope['editor_url'] : $listUrl;
            $requestQuery = trim((string) $request->getQueryString());
            if (! $keepEditor && $requestQuery !== '') {
                $destination .= '?'.$requestQuery;
            }
            if ($root instanceof LinkPage) {
                parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
                if (isset($query['variant']) && is_string($query['variant']) && ctype_digit($query['variant'])) {
                    $destination .= '?variant='.$query['variant'];
                }
            }
            $response->setTargetUrl($destination);
        }

        return $response;
    }
}
