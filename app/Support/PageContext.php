<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Konteks halaman saat ini untuk header (pill lokasi, breadcrumb, dan tab
 * workspace). Satu sumber untuk layout penuh dan layout embed.
 */
class PageContext
{
    /** Apakah request ini dimuat sebagai isi tab (iframe) workspace. */
    public static function isEmbed(Request $request): bool
    {
        if (! config('workspace.enabled', true)) {
            return false;
        }

        if ($request->boolean('embed') || $request->headers->get('X-Workspace-Embed') === '1') {
            return true;
        }

        return strtolower((string) $request->headers->get('Sec-Fetch-Dest')) === 'iframe';
    }

    /**
     * Hasilkan metadata halaman dari route saat ini:
     * sectionLabel, sectionIcon, crumbs, title (nama tab), dan deptPrefix.
     */
    public static function forRequest(Request $request): array
    {
        $currentRoute = $request->route()?->getName() ?? '';
        $user = $request->user();
        $crumbs = [];

        $canPrice = $user ? $user->can('manage-price-requests') : false;
        $canSales = $user ? $user->can('manage-sales') : false;
        $canAdmin = $user ? $user->can('manage-admin') : false;

        $crumbMap = [
            ['dev.worklog',                     ['Admin Panel', 'Rekap Progres']],
            ['manage-sales.activity-log',       ['Management', 'Activity Log']],
            ['manage-sales.kpi',                ['Management', 'Sales KPI & Target']],
            ['manage-sales',                    ['Management', 'Manage Sales']],
            ['manage.marketing',                ['Management', 'Manage Marketing']],
            ['manage.technical',                ['Management', 'Manage Technical']],
            ['manage.admin',                    ['Management', 'Manage Admin']],
            ['teknisi.dashboard',               ['Teknisi', 'Dashboard']],
            ['teknisi.tasks',                   ['Teknisi', 'My Tasks']],
            ['teknisi.field-work',              ['Teknisi', 'Field Work']],
            ['teknisi.requests',                ['Teknisi', 'Request Sales']],
            ['teknisi.price-requests',          ['Teknisi', 'Request Sales']],
            ['teknisi.technical-requests',      ['Teknisi', 'Request Sales']],
            ['teknisi.jadwal',                  ['Teknisi', 'Jadwal']],
            ['teknisi.surveys',                 ['Teknisi', 'Survey']],
            ['teknisi.sizing-projects',         ['Teknisi', 'Sizing Project']],
            ['teknisi.request-hargas',          ['Teknisi', 'Request Harga']],
            ['teknisi.instalasis',              ['Teknisi', 'Instalasi']],
            ['teknisi.documents',               ['Teknisi', 'Document']],
            ['projects',                        ['Teknisi', 'Project']],
            ['project-documents',               ['Teknisi', 'Document']],
            ['marketing.dashboard',             ['Marketing', 'Dashboard']],
            ['whatsapp-center',                 ['Marketing', 'WhatsApp Center']],
            ['sales.dashboard',                 ['Sales', 'Dashboard']],
            ['sales.my-leads',                  ['Sales', 'My Leads']],
            ['sales.follow-ups',                ['Sales', 'Follow Up']],
            ['sales.meetings',                  ['Sales', 'Meeting']],
            ['sales.schedules',                 ['Sales', 'Schedule']],
            ['sales.proposals',                 ['Sales', 'Quotation']],
            ['sales.requests',                  ['Sales', 'Request']],
            ['estimator.price-history',         ['Estimator', 'Price History']],
            ['estimator.suppliers',             ['Estimator', 'Suppliers']],
            ['estimator',                       [$canPrice ? 'Estimator' : 'Sales', $canPrice ? 'Queue' : 'Request']],
            ['products',                        [$canPrice && ! $canSales && ! $canAdmin ? 'Pricelist' : 'Products']],
            ['sales.technical-requests',        ['Sales', 'Technical Request']],
            ['sales',                           ['Sales']],
            ['leads.pipeline',                  ['Sales', 'Pipeline']],
            ['leads.activities',                ['Marketing', 'Log Aktivitas']],
            ['leads.monitoring',                ['Marketing', 'Monitoring']],
            ['leads',                           ['Marketing', 'Lead / Opportunity']],
            ['partners',                        ['Marketing', 'Data Partner']],
            ['marketing',                       ['Marketing']],
            ['monitoring',                      ['Monitoring']],
            ['lead-tasks',                      ['Sales', 'Inside Sales']],
            ['admin-panel.index',               ['Admin Panel', 'User Management']],
            ['admin-panel.account-managers',    ['Admin Panel', 'Account Manager']],
            ['admin-panel.work-types',          ['Admin Panel', 'Work Type']],
            ['admin-panel.document-categories', ['Admin Panel', 'Document Category']],
            ['admin-panel.project-statuses',    ['Admin Panel', 'Project Status']],
            ['admin-panel.audit-log',           ['Admin Panel', 'Audit Log']],
            ['admin-panel.users',               ['Admin Panel', 'User Management']],
            ['admin-panel.roles',               ['Admin Panel', 'User Management']],
            ['admin-panel',                     ['Admin Panel']],
            ['admin.hub',                       ['Admin', 'Dashboard']],
            ['admin.purchasing.reports',        ['Admin', 'Procurement Reports']],
            ['admin.purchasing',                ['Admin', 'Customer PO']],
            ['admin.supplier-pos',              ['Admin', 'Supplier PO']],
            ['admin.suppliers',                 ['Admin', 'Suppliers']],
            ['admin.goods-receipts',            ['Admin', 'Goods Receipts']],
            ['admin.goods-deliveries',          ['Admin', 'Deliveries']],
            ['admin.supplier-invoices',         ['Admin', 'Supplier Invoices']],
            ['admin.bast',                      ['Admin', 'BAST']],
            ['admin.invoices',                  ['Admin', 'Invoice']],
            ['admin.pos',                       ['Admin', 'PO Legacy']],
            ['admin.payments',                  ['Admin', 'Payment']],
            ['admin',                           ['Admin']],
            ['trash',                           ['Trash']],
            ['knowledge-base',                  ['Knowledge Base']],
            ['product-knowledge',               ['Product KB']],
            ['customers',                       ['Customer']],
            ['customer-contacts',               ['Customer', 'Contact']],
            ['documents',                       ['Documents']],
            ['ai.assistant',                    ['AI Assistant']],
            ['settings',                        ['Settings']],
            ['profile',                         ['Profile']],
        ];

        $plainActions = ['index', 'store', 'update', 'destroy', 'login', 'logout'];
        $actionLabels = [
            'create' => 'Add', 'edit' => 'Edit', 'show' => 'Detail',
            'price.edit' => 'Update Price',
            'import' => 'Import', 'import.execute' => 'Import',
            'preview' => 'Preview', 'convert' => 'Convert', 'export' => 'Export',
        ];
        $groupIconMap = [
            'Management' => 'briefcase', 'Teknisi' => 'tools', 'Marketing' => 'megaphone',
            'Sales' => 'calendar', 'Admin' => 'receipt', 'Admin Panel' => 'settings',
            'Estimator' => 'tools',
            'Customer' => 'users', 'Monitoring' => 'activity', 'Dashboard' => 'grid',
            'Documents' => 'folder', 'Pricelist' => 'cube', 'Products' => 'cube',
            'Trash' => 'trash', 'Knowledge Base' => 'book', 'Product KB' => 'cube',
            'Settings' => 'settings', 'Profile' => 'user', 'AI Assistant' => 'message',
            'Developer' => 'code',
        ];
        $deptShortMap = [
            'Management' => 'Mgmt',
            'Teknisi' => 'Eng',
            'Marketing' => 'Mkt',
            'Sales' => 'Sales',
            'Estimator' => 'Est',
            'Admin' => 'Admin',
            'Admin Panel' => 'Panel',
        ];

        $matched = null;
        foreach ($crumbMap as $entry) {
            if ($currentRoute !== '' && str_starts_with($currentRoute, $entry[0])) {
                $matched = $entry;
                break;
            }
        }

        $groupRaw = 'Dashboard';
        if ($matched !== null) {
            [$prefix, $labels] = $matched;
            $groupRaw = $labels[0];
            foreach (array_slice($labels, 1) as $label) {
                $crumbs[] = ['label' => __($label), 'url' => null];
            }
            $rest = ltrim(substr($currentRoute, strlen($prefix)), '.');
            $hasActionCrumb = false;
            if ($rest !== '' && ! in_array($rest, $plainActions, true)) {
                $action = $actionLabels[$rest] ?? Str::headline(str_replace(['-', '_'], ' ', $rest));
                $crumbs[] = ['label' => __($action), 'url' => null];
                $hasActionCrumb = true;
            }
            if ($hasActionCrumb && count($crumbs) >= 2) {
                $indexRoute = $prefix.'.index';
                $indexPos = count($crumbs) - 2;
                if (Route::has($indexRoute)) {
                    $crumbs[$indexPos]['url'] = route($indexRoute);
                }
            }
            $sectionLabel = __($labels[0]);
            $sectionIcon = $groupIconMap[$labels[0]] ?? 'grid';
        } else {
            $routeParts = explode('.', $currentRoute);
            $section = $routeParts[0] ?? 'dashboard';
            if ($section !== 'dashboard' && $section !== '') {
                $sectionLabel = ucwords(str_replace(['-', '_'], ' ', $section));
                $action = end($routeParts);
                if ($action !== '' && $action !== 'index' && $action !== $section) {
                    $crumbs[] = [
                        'label' => $actionLabels[$action] ?? ucwords(str_replace(['-', '_'], ' ', $action)),
                        'url' => null,
                    ];
                }
            } else {
                $sectionLabel = __('Dashboard');
            }
            $sectionIcon = 'grid';
        }

        $dept = $deptShortMap[$groupRaw] ?? null;
        $leaf = count($crumbs) > 0 ? $crumbs[count($crumbs) - 1]['label'] : $sectionLabel;
        if (count($crumbs) >= 2) {
            $leaf = $crumbs[0]['label'].' / '.$crumbs[count($crumbs) - 1]['label'];
        }
        $tabTitle = $dept && $leaf !== $sectionLabel ? $dept.' • '.$leaf : $leaf;

        return [
            'route' => $currentRoute,
            'url' => $request->getRequestUri(),
            'path' => '/'.ltrim($request->path(), '/'),
            'sectionLabel' => $sectionLabel,
            'sectionIcon' => $sectionIcon,
            'crumbs' => $crumbs,
            'tabTitle' => $tabTitle,
            'dept' => $dept,
        ];
    }
}
