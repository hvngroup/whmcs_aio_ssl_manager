<?php
/**
 * Base Controller — Abstract base for all admin controllers
 *
 * Provides: template rendering, JSON responses, pagination,
 * settings access, CSRF validation, and common utilities.
 *
 * @package    AioSSL\Controller
 * @author     HVN GROUP <dev@hvn.vn>
 * @copyright  2026 HVN GROUP (https://hvn.vn)
 */

namespace AioSSL\Controller;

use WHMCS\Database\Capsule;

abstract class BaseController
{
    /** @var array Module vars from WHMCS */
    protected $vars;

    /** @var array Language strings */
    protected $lang;

    /** @var string Module link base URL */
    protected $moduleLink;

    /** @var int Default items per page */
    protected $perPage = 25;

    /**
     * @param array $vars Module configuration variables
     * @param array $lang Language strings
     */
    public function __construct(array $vars, array $lang)
    {
        $this->vars = $vars;
        $this->lang = $lang;
        $this->moduleLink = 'addonmodules.php?module=aio_ssl_admin';

        // Load items per page from settings
        $ipp = $this->getSetting('items_per_page');
        if ($ipp && (int)$ipp > 0) {
            $this->perPage = (int)$ipp;
        }
    }

    /**
     * Render the main page output
     *
     * @param string $action
     * @return void
     */
    abstract public function render(string $action = ''): void;

    /**
     * Handle AJAX requests
     *
     * @param string $action
     * @return array JSON-serializable response
     */
    public function handleAjax(string $action = ''): array
    {
        return ['success' => false, 'message' => 'Not implemented'];
    }

    // ─── Template Rendering ────────────────────────────────────────

    /**
     * Render a PHP template via extract() + include
     *
     * CONSTRAINT C1: WHMCS admin addon does NOT support Smarty.
     * All templates must be plain .php files using <?= ?> for output.
     *
     * @param string $template Template filename (e.g. 'dashboard.php')
     * @param array  $data     Variables to pass to template
     * @return void
     */

    protected function renderTemplate(string $template, array $data = []): void
    {
        $templatePath = AIO_SSL_TEMPLATE_PATH . '/' . $template;

        if (!file_exists($templatePath)) {
            echo '<div class="alert alert-danger">Template not found: ' . htmlspecialchars($template) . '</div>';
            return;
        }

        // Inject common variables
        $data['moduleLink'] = $this->moduleLink;
        $data['moduleVersion'] = AIO_SSL_VERSION;
        $data['lang'] = $this->lang;
        $data['csrfToken'] = generate_token('plain');

        // Extract variables for template
        extract($data, EXTR_SKIP);

        // Render
        ob_start();
        include $templatePath;
        echo ob_get_clean();
    }

    /**
     * Output JSON response (for non-AJAX calls that need JSON)
     *
     * @param array $data
     * @param int   $code HTTP status code
     * @return void
     */
    protected function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // ─── Settings Access ───────────────────────────────────────────

    /**
     * Get a module setting value
     *
     * @param string      $key
     * @param string|null $default
     * @return string|null
     */
    protected function getSetting(string $key, ?string $default = null): ?string
    {
        try {
            $row = Capsule::table('mod_aio_ssl_settings')
                ->where('setting', $key)
                ->first();
            return $row ? $row->value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Set a module setting value
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    protected function setSetting(string $key, string $value): void
    {
        Capsule::table('mod_aio_ssl_settings')->updateOrInsert(
            ['setting' => $key],
            ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }

    // ─── Pagination ────────────────────────────────────────────────

    /**
     * Calculate pagination parameters
     *
     * @param int $totalItems
     * @param int $currentPage
     * @return array ['offset'=>int, 'limit'=>int, 'totalPages'=>int, 'currentPage'=>int, 'total'=>int]
     */
    protected function paginate(int $totalItems, int $currentPage = 1): array
    {
        $currentPage = max(1, $currentPage);
        $totalPages = max(1, (int)ceil($totalItems / $this->perPage));
        $currentPage = min($currentPage, $totalPages);

        return [
            'offset'      => ($currentPage - 1) * $this->perPage,
            'limit'       => $this->perPage,
            'totalPages'  => $totalPages,
            'currentPage' => $currentPage,
            'total'       => $totalItems,
            'perPage'     => $this->perPage,
        ];
    }

    /**
     * Render pagination HTML (Bootstrap 3 compatible)
     *
     * @param array  $pagination From paginate()
     * @param string $baseUrl
     * @return string HTML
     */
    protected function renderPagination(array $pagination, string $baseUrl): string
    {
        if ($pagination['totalPages'] <= 1) {
            return '';
        }

        $html = '<div class="text-center"><ul class="pagination">';

        // Previous
        if ($pagination['currentPage'] > 1) {
            $html .= '<li><a href="' . $baseUrl . '&p=' . ($pagination['currentPage'] - 1) . '">&laquo;</a></li>';
        } else {
            $html .= '<li class="disabled"><span>&laquo;</span></li>';
        }

        // Page numbers
        $start = max(1, $pagination['currentPage'] - 3);
        $end = min($pagination['totalPages'], $pagination['currentPage'] + 3);

        if ($start > 1) {
            $html .= '<li><a href="' . $baseUrl . '&p=1">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="disabled"><span>...</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $pagination['currentPage']) ? ' class="active"' : '';
            $html .= '<li' . $active . '><a href="' . $baseUrl . '&p=' . $i . '">' . $i . '</a></li>';
        }

        if ($end < $pagination['totalPages']) {
            if ($end < $pagination['totalPages'] - 1) {
                $html .= '<li class="disabled"><span>...</span></li>';
            }
            $html .= '<li><a href="' . $baseUrl . '&p=' . $pagination['totalPages'] . '">' . $pagination['totalPages'] . '</a></li>';
        }

        // Next
        if ($pagination['currentPage'] < $pagination['totalPages']) {
            $html .= '<li><a href="' . $baseUrl . '&p=' . ($pagination['currentPage'] + 1) . '">&raquo;</a></li>';
        } else {
            $html .= '<li class="disabled"><span>&raquo;</span></li>';
        }

        $html .= '</ul></div>';
        $html .= '<p class="text-muted text-center" style="font-size:12px;">Showing '
               . (($pagination['offset'] + 1)) . '–'
               . min($pagination['offset'] + $pagination['perPage'], $pagination['total'])
               . ' of ' . $pagination['total'] . '</p>';

        return $html;
    }

    // ─── Input & Security ──────────────────────────────────────────

    /**
     * Get sanitized request parameter
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function input(string $key, $default = '')
    {
        $val = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
        if (is_string($val)) {
            return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
        }
        return $val;
    }

    /**
     * Get raw (unsanitized) input — for CSR, JSON, etc.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function rawInput(string $key, $default = '')
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    }

    /**
     * Validate CSRF token
     *
     * @return bool
     */
    protected function validateCsrf(): bool
    {
        $token = $this->input('token', '');
        if (empty($token)) {
            return false;
        }
        // WHMCS token validation
        return (function_exists('verify_hash') && verify_hash($token));
    }

    // ─── Helpers ───────────────────────────────────────────────────

    /**
     * Get the current page number from request
     *
     * @return int
     */
    protected function getCurrentPage(): int
    {
        return max(1, (int)$this->input('p', 1));
    }

    /**
     * Get language string with fallback
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    protected function t(string $key, string $default = ''): string
    {
        return isset($this->lang[$key]) ? $this->lang[$key] : ($default ?: $key);
    }

    /**
     * Redirect to module page
     *
     * @param string $page
     * @param array  $params Extra query params
     * @return void
     */
    protected function redirect(string $page, array $params = []): void
    {
        $url = $this->moduleLink . '&page=' . $page;
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        header('Location: ' . $url);
        exit;
    }
}