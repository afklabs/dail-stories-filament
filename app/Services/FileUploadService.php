<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * File Upload Service
 * 
 * Handles secure file uploads with validation, processing, and storage.
 * Provides avatar upload, image processing, and file management functionality.
 * 
 * Security Features:
 * - MIME type validation
 * - File extension validation
 * - File size limits
 * - Path traversal prevention
 * - Image processing and optimization
 * 
 * @author Development Team
 * @version 1.0.0
 */
class FileUploadService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Upload and process avatar image
     * 
     * @param UploadedFile $file
     * @param int $userId
     * @return array Upload result with file info
     * @throws \Exception
     */
    public function uploadAvatar(UploadedFile $file, int $userId): array
    {
        // Validate file security
        $this->validateImageFile($file);

        // Generate secure filename
        $filename = $this->generateSecureFilename($file, $userId, 'avatar');
        $relativePath = 'avatars/' . $filename;
        
        // Process and resize image
        $processedImage = $this->processAvatarImage($file);
        
        // Store the processed image
        Storage::disk('public')->put($relativePath, $processedImage);
        
        // Generate full URL
        $url = asset('storage/' . $relativePath);

        return [
            'path' => $relativePath,
            'url' => $url,
            'filename' => $filename,
            'size' => strlen($processedImage),
            'mime_type' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Delete file from storage
     * 
     * @param string $path
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->delete($path);
            }
            return true; // File doesn't exist, consider it deleted
        } catch (\Exception $e) {
            \Log::error('Failed to delete file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate uploaded image file
     * 
     * @param UploadedFile $file
     * @throws \Exception
     */
    private function validateImageFile(UploadedFile $file): void
    {
        // Check if file was uploaded successfully
        if (!$file->isValid()) {
            throw new \Exception('File upload failed');
        }

        // Validate MIME type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \Exception('Invalid file type. Only JPEG, PNG, JPG, and WebP images are allowed.');
        }

        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception('Invalid file extension');
        }

        // Check file size (2MB limit)
        $maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size too large. Maximum allowed size is 2MB.');
        }

        // Validate image dimensions
        $imageInfo = getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new \Exception('Invalid image file');
        }

        [$width, $height] = $imageInfo;
        
        // Minimum dimensions
        if ($width < 200 || $height < 200) {
            throw new \Exception('Image must be at least 200x200 pixels');
        }

        // Maximum dimensions
        if ($width > 2000 || $height > 2000) {
            throw new \Exception('Image cannot exceed 2000x2000 pixels');
        }
    }

    /**
     * Generate secure filename
     * 
     * @param UploadedFile $file
     * @param int $userId
     * @param string $type
     * @return string
     */
    private function generateSecureFilename(UploadedFile $file, int $userId, string $type): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $timestamp = now()->format('Y-m-d_H-i-s');
        $randomString = Str::random(8);
        
        return "{$type}_{$userId}_{$timestamp}_{$randomString}.{$extension}";
    }

    /**
     * Process avatar image (resize, optimize)
     * 
     * @param UploadedFile $file
     * @return string Processed image data
     */
    private function processAvatarImage(UploadedFile $file): string
    {
        try {
            // Read and process image
            $image = $this->imageManager->read($file->getPathname());
            
            // Resize to standard avatar size while maintaining aspect ratio
            $image->scale(width: 400, height: 400);
            
            // Convert to JPEG for consistency and compression
            $processedImage = $image->toJpeg(quality: 85);
            
            return $processedImage;
            
        } catch (\Exception $e) {
            \Log::error('Image processing failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);
            
            // Fallback: return original file content
            return file_get_contents($file->getPathname());
        }
    }

    /**
     * Get file info from storage
     * 
     * @param string $path
     * @return array|null
     */
    public function getFileInfo(string $path): ?array
    {
        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }

            return [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'size' => Storage::disk('public')->size($path),
                'last_modified' => Storage::disk('public')->lastModified($path),
                'exists' => true,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean up old avatar files for a user
     * 
     * @param int $userId
     * @param string $currentAvatar
     * @return void
     */
    public function cleanupOldAvatars(int $userId, string $currentAvatar = null): void
    {
        try {
            $avatarDir = 'avatars/';
            $pattern = "avatar_{$userId}_*";
            
            $files = Storage::disk('public')->files($avatarDir);
            
            foreach ($files as $file) {
                $filename = basename($file);
                if (fnmatch($pattern, $filename) && $file !== $currentAvatar) {
                    Storage::disk('public')->delete($file);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to cleanup old avatars', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}