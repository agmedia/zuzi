<?php

namespace Tests\Unit;

use App\Services\Catalog\ImportFilterMemory;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;

class ImportFilterMemoryTest extends TestCase
{
    private ImportFilterMemory $memory;

    private Store $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memory = new ImportFilterMemory();
        $this->session = new Store('import-filter-test', new ArraySessionHandler(120));
        $this->session->start();
    }

    public function test_it_restores_remembered_filters_without_overwriting_other_query_values(): void
    {
        $filteredRequest = $this->request(
            '/admin/catalog/delfi-import?search=Se%C4%8Divo&source_category=Knjiga'
            . '&source_genre=Filozofija&status=new&page=3'
        );
        $this->memory->remember($filteredRequest, 'delfi');

        $returnRequest = $this->request('/admin/catalog/delfi-import?tab=settings');

        $this->assertFalse($this->memory->restore($returnRequest, 'delfi'));
        $this->assertSame('Sečivo', $returnRequest->query('search'));
        $this->assertSame('Knjiga', $returnRequest->query('source_category'));
        $this->assertSame('Filozofija', $returnRequest->query('source_genre'));
        $this->assertSame('new', $returnRequest->query('status'));
        $this->assertSame('settings', $returnRequest->query('tab'));
        $this->assertNull($returnRequest->query('page'));
    }

    public function test_an_explicit_filter_submission_replaces_the_previous_filters(): void
    {
        $this->memory->remember($this->request(
            '/admin/catalog/delfi-import?search=Se%C4%8Divo&source_category=Knjiga&status=new'
        ), 'delfi');

        $replacement = $this->request('/admin/catalog/delfi-import?search=&status=all');
        $this->assertFalse($this->memory->restore($replacement, 'delfi'));
        $this->memory->remember($replacement, 'delfi');

        $returnRequest = $this->request('/admin/catalog/delfi-import');
        $this->memory->restore($returnRequest, 'delfi');

        $this->assertSame('all', $returnRequest->query('status'));
        $this->assertNull($returnRequest->query('search'));
        $this->assertNull($returnRequest->query('source_category'));
    }

    public function test_clear_request_forgets_only_that_import_modules_filters(): void
    {
        $this->memory->remember($this->request(
            '/admin/catalog/delfi-import?source_category=Knjiga&status=new'
        ), 'delfi');
        $this->memory->remember($this->request(
            '/admin/catalog/znanje-import?source_category=Strane%20knjige&status=all'
        ), 'znanje');

        $clearRequest = $this->request('/admin/catalog/delfi-import?clear_filters=1');

        $this->assertTrue($this->memory->restore($clearRequest, 'delfi'));
        $this->assertSame([], $this->session->get('catalog_import_filters.delfi', []));
        $this->assertSame('Strane knjige', $this->session->get(
            'catalog_import_filters.znanje.source_category'
        ));
    }

    private function request(string $uri): Request
    {
        $request = Request::create($uri);
        $request->setLaravelSession($this->session);

        return $request;
    }
}
