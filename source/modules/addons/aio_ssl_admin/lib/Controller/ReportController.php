<?php

namespace AioSSL\Controller;

class ReportController extends BaseController
{
    public function render(string $action = ''): void
    {
        $this->renderTemplate('reports.tpl', []);
    }
}