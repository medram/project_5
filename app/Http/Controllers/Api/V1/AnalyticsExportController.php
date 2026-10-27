<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AnalyticsExportController as CsvExportController;
use App\Http\Requests\AnalyticsFilterRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportController extends ApiController
{
    public function __invoke(AnalyticsFilterRequest $request, CsvExportController $export): StreamedResponse
    {
        return $export->forWorkspace($this->workspace($request), $request->filters());
    }
}
