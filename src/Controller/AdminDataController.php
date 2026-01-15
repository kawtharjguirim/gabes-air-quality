<?php

namespace App\Controller;

use App\Service\DataImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/data')]
#[IsGranted('ROLE_USER')] // Change to ROLE_ADMIN in production
final class AdminDataController extends AbstractController
{
    public function __construct(
        private DataImportService $dataImportService
    ) {
    }

    /**
     * Get data quality metrics
     */
    #[Route('/quality', name: 'admin_data_quality', methods: ['GET'])]
    public function quality(): JsonResponse
    {
        $quality = $this->dataImportService->getDataQuality();

        return $this->json($quality);
    }

    /**
     * Upload CSV file
     */
    #[Route('/upload', name: 'admin_data_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        // Validate file
        $validation = $this->dataImportService->validateCsvFormat($file);

        if (!$validation['valid']) {
            return $this->json([
                'error' => 'Invalid CSV format',
                'details' => $validation['errors']
            ], 400);
        }

        // Import data
        try {
            $result = $this->dataImportService->importFromCsv($file);

            return $this->json([
                'message' => 'File uploaded successfully',
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors']
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Import failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data preview
     */
    #[Route('/preview', name: 'admin_data_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
        $limit = (int)$request->query->get('limit', 10);

        $data = $this->dataImportService->getDataPreview($limit);

        return $this->json([
            'data' => $data,
            'count' => count($data)
        ]);
    }
}
