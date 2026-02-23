<?php

namespace AioSSL\Controller;

class ImportController extends BaseController
{
    public function render(string $action = ''): void
    {
        $this->renderTemplate('import.tpl', []);
    }
}