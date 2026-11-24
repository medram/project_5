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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopedAdminContent
{
    public const ATTRIBUTE = 'admin_content_scope';

    /**
     * @var array<string, array{model: class-string<Model>, manage: PlatformPermission, delete: PlatformPermission, destroy: string, list: string}>
     */
    private const RESOURCES = [
        'link' => [
            'model' => Link::class,
            'manage' => PlatformPermission::AdminLinksManage,
            'delete' => PlatformPermission::AdminLinksDelete,
            'destroy' => 'admin.content.links.destroy',
            'list' => '/admin/links',
        ],
        'bioPage' => [
            'model' => BioPage::class,
            'manage' => PlatformPermission::AdminBioPagesManage,
            'delete' => PlatformPermission::AdminBioPagesDelete,
            'destroy' => 'admin.content.biolinks.destroy',
            'list' => '/admin/biolinks',
        ],
        'qrCode' => [
            'model' => QrCode::class,
            'manage' => PlatformPermission::AdminQrCodesManage,
            'delete' => PlatformPermission::AdminQrCodesDelete,
            'destroy' => 'admin.content.qr-codes.destroy',
            'list' => '/admin/qr-codes',
        ],
        'folder' => [
            'model' => Folder::class,
            'manage' => PlatformPermission::AdminFoldersManage,
            'delete' => PlatformPermission::AdminFoldersDelete,
            'destroy' => 'admin.content.folders.destroy',
            'list' => '/admin/folders',
        ],
        'customDomain' => [
            'model' => CustomDomain::class,
            'manage' => PlatformPermission::AdminDomainsManage,
            'delete' => PlatformPermission::AdminDomainsDelete,
            'destroy' => 'admin.content.domains.destroy',
            'list' => '/admin/custom-domains',
        ],
        'linkOverlay' => [
            'model' => LinkOverlay::class,
            'manage' => PlatformPermission::AdminOverlaysManage,
            'delete' => PlatformPermission::AdminOverlaysDelete,
            'destroy' => 'admin.content.overlays.destroy',
            'list' => '/admin/link-overlays',
        ],
        'linkPage' => [
            'model' => LinkPage::class,
            'manage' => PlatformPermission::AdminLinkPagesManage,
            'delete' => PlatformPermission::AdminLinkPagesDelete,
            'destroy' => 'admin.content.link-pages.destroy',
            'list' => '/admin/link-pages',
        ],
        'trackingPixel' => [
            'model' => TrackingPixel::class,
            'manage' => PlatformPermission::AdminPixelsManage,
            'delete' => PlatformPermission::AdminPixelsDelete,
            'destroy' => 'admin.content.pixels.destroy',
            'list' => '/admin/pixels',
        ],
    ];

    public function __construct(
        private readonly PlatformAuthorization $authorization,
        private readonly AdminContentMutation $mutations,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $matches = [];
        foreach (self::RESOURCES as $parameter => $definition) {
            $root = $request->route($parameter);
            if ($root instanceof $definition['model']) {
                $matches[] = [$root, $definition];
            }
        }
        abort_unless(count($matches) === 1, 404);
        [$root, $definition] = $matches[0];

        $routeName = (string) $request->route()?->getName();
        $isDestroy = $routeName === $definition['destroy'];
        $permission = $isDestroy ? $definition['delete'] : $definition['manage'];

        $workspace = $root->workspace()->first();
        abort_if($workspace === null, 404);
        abort_unless($this->authorization->allows($request->user(), $permission), 403);

        $scope = [
            'root' => $root,
            'workspace' => $workspace,
            'actor_id' => $request->user()->getKey(),
            'permission' => $permission,
            'list_url' => $definition['list'],
            'editor_url' => $definition['list'].'/'.$root->getRouteKey(),
        ];
        $request->attributes->set(self::ATTRIBUTE, $scope);

        if ($request->isMethodSafe() || $request->routeIs('admin.content.domains.verify')) {
            return $next($request);
        }

        $response = $this->mutations->run($request, $root, $routeName, fn () => $next($request));
        if ($response instanceof RedirectResponse && $response->exception === null && $response->getStatusCode() < 400) {
            $keepEditor = ! $isDestroy && (
                $root instanceof BioPage
                || $root instanceof LinkPage
                || $request->routeIs('admin.content.domains.reissue', 'admin.content.pixels.rules.*')
            );

            $destination = $scope['editor_url'];
            $insightsPath = $definition['list'].'/'.$root->getRouteKey().'/insights';
            $referer = $request->headers->get('referer');
            $refererPath = is_string($referer) ? parse_url($referer, PHP_URL_PATH) : null;
            $returnToInsights = is_string($refererPath) && $refererPath === $insightsPath;

            if (! $keepEditor) {
                parse_str((string) $request->getQueryString(), $query);
                unset($query['page']);
                $remaining = http_build_query($query);
                $destination = $returnToInsights
                    ? $insightsPath.($remaining === '' ? '' : '?'.$remaining)
                    : $definition['list'].($remaining === '' ? '' : '?'.$remaining);
            }

            if (! $isDestroy && $root instanceof LinkPage) {
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
