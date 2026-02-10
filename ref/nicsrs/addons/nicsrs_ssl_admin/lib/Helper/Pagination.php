<?php
/**
 * Pagination Helper
 * Generates pagination links
 * 
 * @package    nicsrs_ssl_admin
 * @author     HVN GROUP
 * @copyright  Copyright (c) HVN GROUP (https://hvn.vn)
 */

namespace NicsrsAdmin\Helper;

class Pagination
{
    /**
     * @var int Total items
     */
    private $total;
    
    /**
     * @var int Items per page
     */
    private $perPage;
    
    /**
     * @var int Current page
     */
    private $currentPage;
    
    /**
     * @var string Base URL
     */
    private $baseUrl;
    
    /**
     * @var array Additional URL parameters
     */
    private $params;

    /**
     * Constructor
     * 
     * @param int $total Total items
     * @param int $perPage Items per page
     * @param int $currentPage Current page number
     * @param string $baseUrl Base URL for links
     * @param array $params Additional URL parameters
     */
    public function __construct(
        int $total,
        int $perPage,
        int $currentPage,
        string $baseUrl = '',
        array $params = []
    ) {
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->baseUrl = $baseUrl;
        $this->params = $params;
    }

    /**
     * Get total number of pages
     * 
     * @return int Total pages
     */
    public function getTotalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    /**
     * Check if there's a previous page
     * 
     * @return bool Has previous
     */
    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Check if there's a next page
     * 
     * @return bool Has next
     */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    /**
     * Build URL for page
     * 
     * @param int $page Page number
     * @return string URL
     */
    public function getPageUrl(int $page): string
    {
        $params = array_merge($this->params, ['page' => $page]);
        $query = http_build_query($params);
        
        return $this->baseUrl . ($query ? '&' . $query : '');
    }

    /**
     * Get page numbers to display
     * 
     * @param int $range Number of pages to show on each side
     * @return array Page numbers
     */
    public function getPageNumbers(int $range = 2): array
    {
        $totalPages = $this->getTotalPages();
        
        if ($totalPages <= 1) {
            return [];
        }

        $pages = [];
        
        // Always include first page
        $pages[] = 1;
        
        // Calculate range around current page
        $start = max(2, $this->currentPage - $range);
        $end = min($totalPages - 1, $this->currentPage + $range);
        
        // Add ellipsis after first page if needed
        if ($start > 2) {
            $pages[] = '...';
        }
        
        // Add pages in range
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        
        // Add ellipsis before last page if needed
        if ($end < $totalPages - 1) {
            $pages[] = '...';
        }
        
        // Always include last page if more than 1 page
        if ($totalPages > 1) {
            $pages[] = $totalPages;
        }

        return $pages;
    }

    /**
     * Render pagination HTML (continued)
     * 
     * @return string HTML
     */
    public function render(): string
    {
        $totalPages = $this->getTotalPages();
        
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav><ul class="pagination">';
        
        // Previous button
        if ($this->hasPrevious()) {
            $html .= sprintf(
                '<li><a href="%s">&laquo; Prev</a></li>',
                htmlspecialchars($this->getPageUrl($this->currentPage - 1))
            );
        } else {
            $html .= '<li class="disabled"><span>&laquo; Prev</span></li>';
        }
        
        // Page numbers
        foreach ($this->getPageNumbers() as $page) {
            if ($page === '...') {
                $html .= '<li class="disabled"><span>...</span></li>';
            } elseif ($page == $this->currentPage) {
                $html .= sprintf('<li class="active"><span>%d</span></li>', $page);
            } else {
                $html .= sprintf(
                    '<li><a href="%s">%d</a></li>',
                    htmlspecialchars($this->getPageUrl($page)),
                    $page
                );
            }
        }
        
        // Next button
        if ($this->hasNext()) {
            $html .= sprintf(
                '<li><a href="%s">Next &raquo;</a></li>',
                htmlspecialchars($this->getPageUrl($this->currentPage + 1))
            );
        } else {
            $html .= '<li class="disabled"><span>Next &raquo;</span></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }

    /**
     * Get info text (e.g., "Showing 1-25 of 100")
     * 
     * @return string Info text
     */
    public function getInfo(): string
    {
        if ($this->total === 0) {
            return 'No results found';
        }

        $start = (($this->currentPage - 1) * $this->perPage) + 1;
        $end = min($start + $this->perPage - 1, $this->total);
        
        return sprintf('Showing %d-%d of %d', $start, $end, $this->total);
    }

    /**
     * Get current page
     * 
     * @return int Current page
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get total items
     * 
     * @return int Total items
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Get items per page
     * 
     * @return int Per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get offset for database query
     * 
     * @return int Offset
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }
}