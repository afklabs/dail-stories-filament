<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\RuntimeException as ImageRuntimeException;

/**
 * Enhanced File Upload Service
 * 
 * Handles secure file uploads with comprehensive validation, processing, and storage.
 * Provides avatar upload, image processing, file management, and cleanup functionality.
 * 
 * Security Features:
 * - MIME type validation with header inspection
 * - File extension validation
 * - File size limits with configurable max sizes
 * - Path traversal prevention
 * - Image processing and optimization
 * - Malicious file detection
 * - Directory creation protection
 * 
 * Performance Features:
 * - Efficient image processing with fallback
 * - Memory-optimized file handling
 * - Batch cleanup operations
 * - Storage optimization
 * 
 * @author Development Team
 * @version 2.0.0 - Enhanced with comprehensive error handling
 */
class FileUploadService
{
    private ImageManager $imageManager;

    // Configuration constants
    private const DEFAULT_AVATAR_SIZE = 400;
    private const DEFAULT_JPEG_QUALITY = 85;
    private const DEFAULT_MAX_FILE_SIZE = 2097152; // 2MB in bytes
    private const MIN_IMAGE_DIMENSION = 200;
    private const MAX_IMAGE_DIMENSION = 2000;

    // Allowed file types and extensions
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'image/webp',
        'image/gif'
    ];

    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif'
    ];

    // Dangerous file patterns to block
    private const DANGEROUS_PATTERNS = [
        '<?php',
        '<?=',
        '<script',
        'javascript:',
        'data:',
        'vbscript:'
    ];

    public function __construct()
    {
        try {
            $this->imageManager = new ImageManager(new Driver());
        } catch (\Exception $e) {
            Log::error('Failed to initialize ImageManager', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Image processing service unavailable: ' . $e->getMessage());
        }
    }

    /**
     * Upload and process avatar image with comprehensive validation
     * 
     * @param UploadedFile $file
     * @param int $userId
     * @param array $options Optional configuration overrides
     * @return array Upload result with file info
     * @throws \Exception
     */
    public function uploadAvatar(UploadedFile $file, int $userId, array $options = []): array
    {
        try {
            // Validate file security with comprehensive checks
            $this->validateImageFile($file, $options);

            // Clean up old avatars before creating new one
            $this->cleanupOldAvatars($userId);

            // Generate secure filename with enhanced security
            $filename = $this->generateSecureFilename($file, $userId, 'avatar');
            $relativePath = 'avatars/' . $filename;

            // Ensure directory exists and is secure
            $this->ensureDirectoryExists('avatars');

            // Process and resize image with error handling
            $processedImage = $this->processAvatarImage($file, $options);

            // Store the processed image with validation
            $success = Storage::disk('public')->put($relativePath, $processedImage);

            if (!$success) {
                throw new \Exception('Failed to store processed image');
            }

            // Verify the stored file
            if (!Storage::disk('public')->exists($relativePath)) {
                throw new \Exception('File was not stored successfully');
            }

            // Generate full URL with security check
            $url = $this->generateSecureUrl($relativePath);

            $result = [
                'path' => $relativePath,
                'url' => $url,
                'filename' => $filename,
                'size' => strlen($processedImage),
                'mime_type' => $file->getMimeType(),
                'original_name' => $this->sanitizeFilename($file->getClientOriginalName()),
                'dimensions' => $this->getImageDimensions($processedImage),
                'uploaded_at' => now()->toISOString(),
            ];

            Log::info('Avatar upload successful', [
                'user_id' => $userId,
                'filename' => $filename,
                'size' => $result['size'],
                'mime_type' => $result['mime_type'],
                'path' => $relativePath
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Avatar upload failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName() ?? 'unknown',
                'size' => $file->getSize() ?? 0,
                'mime' => $file->getMimeType() ?? 'unknown'
            ]);
            throw $e;
        }
    }

    /**
     * Upload general file with validation
     * 
     * @param UploadedFile $file
     * @param int $userId
     * @param string $directory
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, int $userId, string $directory = 'uploads', array $options = []): array
    {
        try {
            // Validate general file
            $this->validateFile($file, $options);

            // Generate secure filename
            $filename = $this->generateSecureFilename($file, $userId, 'file');
            $relativePath = $directory . '/' . $filename;

            // Ensure directory exists
            $this->ensureDirectoryExists($directory);

            // Store file directly without processing
            $success = Storage::disk('public')->putFileAs($directory, $file, $filename);

            if (!$success) {
                throw new \Exception('Failed to store file');
            }

            $url = $this->generateSecureUrl($relativePath);

            return [
                'path' => $relativePath,
                'url' => $url,
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'original_name' => $this->sanitizeFilename($file->getClientOriginalName()),
                'uploaded_at' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'user_id' => $userId,
                'directory' => $directory,
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName() ?? 'unknown'
            ]);
            throw $e;
        }
    }

    /**
     * Delete file from storage with enhanced validation
     * 
     * @param string $path
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            // Validate path to prevent directory traversal
            if (!$this->isValidPath($path)) {
                Log::warning('Attempt to delete invalid path', ['path' => $path]);
                return false;
            }

            if (Storage::disk('public')->exists($path)) {
                $deleted = Storage::disk('public')->delete($path);

                if ($deleted) {
                    Log::info('File deleted successfully', ['path' => $path]);
                }

                return $deleted;
            }

            // File doesn't exist, consider it deleted
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate uploaded image file with comprehensive security checks
     * 
     * @param UploadedFile $file
     * @param array $options
     * @throws \Exception
     */
    private function validateImageFile(UploadedFile $file, array $options = []): void
    {
        // Basic file validation first
        $this->validateFile($file, $options);

        // Image-specific validations
        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new \Exception('Invalid image file or corrupted image data');
        }

        [$width, $height, $type] = $imageInfo;

        // Validate image type matches MIME type
        $validImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
        if (!in_array($type, $validImageTypes)) {
            throw new \Exception('Unsupported image type');
        }

        // Check minimum dimensions
        $minDimension = $options['min_dimension'] ?? self::MIN_IMAGE_DIMENSION;
        if ($width < $minDimension || $height < $minDimension) {
            throw new \Exception("Image must be at least {$minDimension}x{$minDimension} pixels (current: {$width}x{$height})");
        }

        // Check maximum dimensions
        $maxDimension = $options['max_dimension'] ?? self::MAX_IMAGE_DIMENSION;
        if ($width > $maxDimension || $height > $maxDimension) {
            throw new \Exception("Image cannot exceed {$maxDimension}x{$maxDimension} pixels (current: {$width}x{$height})");
        }

        // Check aspect ratio if specified
        if (isset($options['aspect_ratio'])) {
            $aspectRatio = $width / $height;
            $expectedRatio = $options['aspect_ratio'];
            $tolerance = $options['aspect_tolerance'] ?? 0.1;

            if (abs($aspectRatio - $expectedRatio) > $tolerance) {
                throw new \Exception('Image aspect ratio does not meet requirements');
            }
        }
    }

    /**
     * Validate general file upload
     * 
     * @param UploadedFile $file
     * @param array $options
     * @throws \Exception
     */
    private function validateFile(UploadedFile $file, array $options = []): void
    {
        // Check if file was uploaded successfully
        if (!$file->isValid()) {
            $error = $file->getError();
            $errorMessage = $this->getUploadErrorMessage($error);
            throw new \Exception("File upload failed: {$errorMessage}");
        }

        // Validate MIME type
        $allowedMimeTypes = $options['allowed_mime_types'] ?? self::ALLOWED_MIME_TYPES;
        $fileMimeType = $file->getMimeType();

        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            throw new \Exception("Invalid file type '{$fileMimeType}'. Allowed types: " . implode(', ', $allowedMimeTypes));
        }

        // Validate file extension
        $allowedExtensions = $options['allowed_extensions'] ?? self::ALLOWED_EXTENSIONS;
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception("Invalid file extension '{$extension}'. Allowed extensions: " . implode(', ', $allowedExtensions));
        }

        // Check file size
        $maxSize = $options['max_size'] ?? self::DEFAULT_MAX_FILE_SIZE;
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 1);
            $fileSizeMB = round($file->getSize() / 1024 / 1024, 1);
            throw new \Exception("File size too large. Maximum allowed: {$maxSizeMB}MB (uploaded: {$fileSizeMB}MB)");
        }

        // Check for dangerous content
        $this->scanForMaliciousContent($file);

        // Validate filename
        $originalName = $file->getClientOriginalName();
        if (!$this->isValidFilename($originalName)) {
            throw new \Exception('Invalid filename detected');
        }
    }

    /**
     * Generate secure filename with collision prevention
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
        $randomString = Str::random(12); // Increased randomness
        $hash = substr(hash('sha256', $file->getPathname() . $userId . microtime()), 0, 8);

        return "{$type}_{$userId}_{$timestamp}_{$randomString}_{$hash}.{$extension}";
    }

    /**
     * Process avatar image with enhanced error handling and fallback
     * 
     * @param UploadedFile $file
     * @param array $options
     * @return string Processed image data
     * @throws \Exception
     */
    private function processAvatarImage(UploadedFile $file, array $options = []): string
    {
        try {
            // Read and process image
            $image = $this->imageManager->read($file->getPathname());

            // Get processing options
            $size = $options['size'] ?? self::DEFAULT_AVATAR_SIZE;
            $quality = $options['quality'] ?? self::DEFAULT_JPEG_QUALITY;

            // Resize to standard avatar size while maintaining aspect ratio
            $image->scale(width: $size, height: $size);

            // Apply sharpening for better quality
            if ($options['sharpen'] ?? true) {
                $image->sharpen(10);
            }

            // Convert to JPEG for consistency and compression
            $encodedImage = $image->toJpeg(quality: $quality);

            // Convert EncodedImageInterface to string
            $processedImage = $encodedImage->toString();

            Log::debug('Image processed successfully', [
                'original_size' => $file->getSize(),
                'processed_size' => strlen($processedImage),
                'compression_ratio' => round((1 - strlen($processedImage) / $file->getSize()) * 100, 2) . '%'
            ]);

            return $processedImage;
        } catch (ImageRuntimeException $e) {
            Log::error('Image processing failed (Intervention)', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            // Try fallback processing with GD directly
            return $this->fallbackImageProcessing($file, $options);
        } catch (\Exception $e) {
            Log::error('Image processing failed (General)', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            throw new \Exception('Image processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Fallback image processing using native GD functions
     * 
     * @param UploadedFile $file
     * @param array $options
     * @return string
     * @throws \Exception
     */
    private function fallbackImageProcessing(UploadedFile $file, array $options = []): string
    {
        try {
            $imageInfo = getimagesize($file->getPathname());
            $mimeType = $imageInfo['mime'];

            // Create image resource based on type
            switch ($mimeType) {
                case 'image/jpeg':
                    $source = imagecreatefromjpeg($file->getPathname());
                    break;
                case 'image/png':
                    $source = imagecreatefrompng($file->getPathname());
                    break;
                case 'image/webp':
                    $source = imagecreatefromwebp($file->getPathname());
                    break;
                default:
                    throw new \Exception('Unsupported image type for fallback processing');
            }

            if (!$source) {
                throw new \Exception('Failed to create image resource');
            }

            // Get dimensions and calculate new size
            $originalWidth = imagesx($source);
            $originalHeight = imagesy($source);
            $size = $options['size'] ?? self::DEFAULT_AVATAR_SIZE;

            // Create new image
            $destination = imagecreatetruecolor($size, $size);

            // Handle transparency for PNG/WebP
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($destination, false);
                imagesavealpha($destination, true);
                $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
                imagefill($destination, 0, 0, $transparent);
            }

            // Resize image
            imagecopyresampled($destination, $source, 0, 0, 0, 0, $size, $size, $originalWidth, $originalHeight);

            // Output to string
            ob_start();
            imagejpeg($destination, null, $options['quality'] ?? self::DEFAULT_JPEG_QUALITY);
            $processedImage = ob_get_clean();

            // Clean up resources
            imagedestroy($source);
            imagedestroy($destination);

            return $processedImage;
        } catch (\Exception $e) {
            Log::error('Fallback image processing failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            // Last resort: return original file content
            return file_get_contents($file->getPathname());
        }
    }

    /**
     * Get file info from storage with enhanced validation
     * 
     * @param string $path
     * @return array|null
     */
    public function getFileInfo(string $path): ?array
    {
        try {
            if (!$this->isValidPath($path) || !Storage::disk('public')->exists($path)) {
                return null;
            }

            $size = Storage::disk('public')->size($path);
            $lastModified = Storage::disk('public')->lastModified($path);
            $url = $this->generateSecureUrl($path);

            return [
                'path' => $path,
                'url' => $url,
                'size' => $size,
                'size_human' => $this->formatBytes($size),
                'last_modified' => $lastModified,
                'last_modified_human' => \Carbon\Carbon::createFromTimestamp($lastModified)->diffForHumans(),
                'exists' => true,
                'extension' => pathinfo($path, PATHINFO_EXTENSION),
                'mime_type' => $this->getMimeTypeFromPath($path),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get file info', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Clean up old avatar files for a user with enhanced pattern matching
     * 
     * @param int $userId
     * @param string $currentAvatar
     * @return int Number of files cleaned up
     */
    public function cleanupOldAvatars(int $userId, ?string $currentAvatar = null): int
    {
        try {
            $avatarDir = 'avatars';
            $pattern = "avatar_{$userId}_*";
            $cleanedCount = 0;

            if (!Storage::disk('public')->exists($avatarDir)) {
                return 0;
            }

            $files = Storage::disk('public')->files($avatarDir);

            foreach ($files as $file) {
                $filename = basename($file);

                // Check if file matches user's avatar pattern and is not the current avatar
                if (fnmatch($pattern, $filename) && $file !== $currentAvatar) {
                    if (Storage::disk('public')->delete($file)) {
                        $cleanedCount++;
                        Log::debug('Cleaned up old avatar', [
                            'user_id' => $userId,
                            'file' => $file
                        ]);
                    }
                }
            }

            if ($cleanedCount > 0) {
                Log::info('Avatar cleanup completed', [
                    'user_id' => $userId,
                    'cleaned_files' => $cleanedCount
                ]);
            }

            return $cleanedCount;
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old avatars', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Bulk cleanup files older than specified days
     * 
     * @param string $directory
     * @param int $daysOld
     * @return int
     */
    public function cleanupOldFiles(string $directory, int $daysOld = 30): int
    {
        try {
            $cutoffTime = now()->subDays($daysOld)->timestamp;
            $cleanedCount = 0;

            if (!Storage::disk('public')->exists($directory)) {
                return 0;
            }

            $files = Storage::disk('public')->files($directory);

            foreach ($files as $file) {
                $lastModified = Storage::disk('public')->lastModified($file);

                if ($lastModified < $cutoffTime) {
                    if (Storage::disk('public')->delete($file)) {
                        $cleanedCount++;
                    }
                }
            }

            Log::info('Bulk file cleanup completed', [
                'directory' => $directory,
                'days_old' => $daysOld,
                'cleaned_files' => $cleanedCount
            ]);

            return $cleanedCount;
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old files', [
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    // ==============================================
    // PRIVATE HELPER METHODS
    // ==============================================

    /**
     * Ensure directory exists and is secure
     * 
     * @param string $directory
     * @throws \Exception
     */
    private function ensureDirectoryExists(string $directory): void
    {
        $fullPath = storage_path('app/public/' . $directory);

        if (!file_exists($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                throw new \Exception("Failed to create directory: {$directory}");
            }
        }

        // Security: Create .htaccess to prevent direct PHP execution
        $htaccessPath = $fullPath . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "Options -Indexes\n<FilesMatch \"\\.(php|php3|php4|php5|phtml)$\">\nDeny from all\n</FilesMatch>";
            file_put_contents($htaccessPath, $htaccessContent);
        }
    }

    /**
     * Generate secure URL for file
     * 
     * @param string $relativePath
     * @return string
     */
    private function generateSecureUrl(string $relativePath): string
    {
        // Remove any potential double slashes or directory traversal
        $cleanPath = preg_replace('/\.\.\//', '', $relativePath);
        $cleanPath = preg_replace('/\/+/', '/', $cleanPath);

        return asset('storage/' . ltrim($cleanPath, '/'));
    }

    /**
     * Validate file path to prevent directory traversal
     * 
     * @param string $path
     * @return bool
     */
    private function isValidPath(string $path): bool
    {
        // Check for directory traversal patterns
        if (strpos($path, '..') !== false || strpos($path, '//') !== false) {
            return false;
        }

        // Check for absolute paths
        if (strpos($path, '/') === 0 || strpos($path, '\\') === 0) {
            return false;
        }

        // Check for protocol patterns
        if (preg_match('/^[a-z]+:\/\//i', $path)) {
            return false;
        }

        return true;
    }

    /**
     * Validate filename for security
     * 
     * @param string $filename
     * @return bool
     */
    private function isValidFilename(string $filename): bool
    {
        // Check for dangerous characters
        if (preg_match('/[<>:"|?*\\\\\/]/', $filename)) {
            return false;
        }

        // Check for dangerous extensions
        $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'cmd', 'scr'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, $dangerousExtensions)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize filename for safe storage
     * 
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Limit length
        if (strlen($filename) > 100) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 90);
            $filename = $name . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Scan file for malicious content
     * 
     * @param UploadedFile $file
     * @throws \Exception
     */
    private function scanForMaliciousContent(UploadedFile $file): void
    {
        // Read first few KB of file for scanning
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            throw new \Exception('Unable to read uploaded file');
        }

        $content = fread($handle, 8192); // Read first 8KB
        fclose($handle);

        // Check for dangerous patterns
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (stripos($content, $pattern) !== false) {
                throw new \Exception('Potentially malicious file content detected');
            }
        }
    }

    /**
     * Get upload error message
     * 
     * @param int $error
     * @return string
     */
    private function getUploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Get image dimensions from processed image data
     * 
     * @param string $imageData
     * @return array|null
     */
    private function getImageDimensions(string $imageData): ?array
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'img_dim_');
            file_put_contents($tempFile, $imageData);

            $imageInfo = getimagesize($tempFile);
            unlink($tempFile);

            if ($imageInfo) {
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'type' => $imageInfo[2]
                ];
            }
        } catch (\Exception $e) {
            Log::debug('Failed to get image dimensions', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get MIME type from file path (fallback method for stored files)
     * 
     * @param string $path
     * @return string
     */
    private function getMimeTypeFromPath(string $path): string
    {
        try {
            // For stored files, use PHP's finfo since Storage::mimeType() is unreliable
            $fullPath = Storage::disk('public')->path($path);
            if (file_exists($fullPath)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fullPath);
                finfo_close($finfo);

                if ($mimeType) {
                    return $mimeType;
                }
            }

            // Fallback based on extension
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'txt' => 'text/plain',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            return $mimeTypes[$extension] ?? 'application/octet-stream';
        } catch (\Exception $e) {
            Log::debug('Failed to get MIME type', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);

            return 'application/octet-stream';
        }
    }

    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 1) . ' ' . $units[$pow];
    }
}
