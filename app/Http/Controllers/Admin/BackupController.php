<?php

namespace App\Http\Controllers\Admin;

use App\Services\BackupService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BackupController extends AdminController
{
    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
        $this->middleware(['permission:settings']);
    }

    /**
     * Get list of available backups
     */
    public function index(): Response
    {
        try {
            $backups = $this->backupService->listBackups();
            return response([
                'status' => true,
                'data' => $backups
            ]);
        } catch (Exception $e) {
            return response([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Create a new backup
     */
    public function store(): Response
    {
        try {
            $result = $this->backupService->createBackup();

            if ($result['success']) {
                return response([
                    'status' => true,
                    'message' => 'Backup created successfully',
                    'data' => $result
                ]);
            }

            return response([
                'status' => false,
                'message' => $result['message'] ?? 'Backup failed'
            ], 422);
        } catch (Exception $e) {
            return response([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Restore from a backup
     */
    public function restore(Request $request): Response
    {
        try {
            $request->validate([
                'filename' => 'required|string'
            ]);

            $result = $this->backupService->restoreBackup($request->filename);

            if ($result['success']) {
                return response([
                    'status' => true,
                    'message' => 'Backup restored successfully',
                    'data' => $result
                ]);
            }

            return response([
                'status' => false,
                'message' => $result['message'] ?? 'Restore failed'
            ], 422);
        } catch (Exception $e) {
            return response([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Download a backup file
     */
    public function download(string $filename): Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            $path = $this->backupService->getBackupPath($filename);

            if (!$path) {
                return response([
                    'status' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            return response()->download($path, $filename, [
                'Content-Type' => 'application/json'
            ]);
        } catch (Exception $e) {
            return response([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Delete a backup file
     */
    public function destroy(string $filename): Response
    {
        try {
            $deleted = $this->backupService->deleteBackup($filename);

            if ($deleted) {
                return response([
                    'status' => true,
                    'message' => 'Backup deleted successfully'
                ]);
            }

            return response([
                'status' => false,
                'message' => 'Backup file not found'
            ], 404);
        } catch (Exception $e) {
            return response([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
