<?php

namespace app\admin\controller;

use app\common\File;
use app\admin\model\File as FileModel;

class FileController extends BaseController
{
    public function upload($key = 'file')
    {
        if ((int) $this->request->post('chunk_total', 0) > 0) {
            return $this->uploadChunk($key);
        }

        $uploadedFile = $this->request->file($key);
        if (!$uploadedFile) {
            $this->error(400, '未找到上传文件');
        }
        $fileIns = new File($uploadedFile);

        $rules = [
            'maxSize' => $fileIns->isImage() ? config('sys.upload.image_size') * 1024 : config('sys.upload.file_size') * 1024,
            'allowExt' => $fileIns->isImage() ? config('sys.upload.image_ext') : config('sys.upload.file_ext')
        ];

        if (!$fileIns->check($rules)) {
            $this->error(403, $fileIns->errorMessage, 'FILE_LIMIT');
        }

        $fileInfo = $fileIns->save();

        if ($fileInfo['is_image'] == true && config('sys.upload.is_thumb') == 1) {
            $thumbWidth = config('sys.upload.thumb_width') ?? 0;
            $thumbHeight = config('sys.upload.thumb_height') ?? 0;
            $thumbQuality = config('sys.upload.thumb_quality') ?? 75;
            $thumbExt = config('sys.upload.thumb_ext') ?? 'jpg';
            $fileInfo['path'] = $this->createThumb($fileInfo['path'], $thumbWidth, $thumbHeight, $thumbQuality, $thumbExt);
            $fileInfo['extension'] = $thumbExt;
        }

        $file = FileModel::create($fileInfo);

        $this->success(201, $file);
    }

    protected function uploadChunk($key = 'file')
    {
        $uploadedFile = $this->request->file($key);
        if (!$uploadedFile) {
            $this->error(400, 'Upload file not found');
        }

        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $this->request->post('upload_id', ''));
        $chunkIndex = (int) $this->request->post('chunk_index', -1);
        $chunkTotal = (int) $this->request->post('chunk_total', 0);
        $fileName = basename((string) $this->request->post('file_name', $uploadedFile->getOriginalName()));
        $fileSize = (int) $this->request->post('file_size', 0);
        $fileMime = (string) $this->request->post('file_mime', $uploadedFile->getOriginalMime());

        if (!$uploadId || $chunkIndex < 0 || $chunkTotal <= 0 || $chunkIndex >= $chunkTotal) {
            $this->error(400, 'Invalid chunk params');
        }

        $chunkDir = app()->getRuntimePath() . 'chunks' . DIRECTORY_SEPARATOR . $uploadId;
        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }

        $chunkPath = $chunkDir . DIRECTORY_SEPARATOR . $chunkIndex . '.part';
        if (!move_uploaded_file($uploadedFile->getRealPath(), $chunkPath)) {
            $this->error(500, 'Save chunk failed');
        }

        for ($i = 0; $i < $chunkTotal; $i++) {
            if (!is_file($chunkDir . DIRECTORY_SEPARATOR . $i . '.part')) {
                $this->success(202, [
                    'upload_id' => $uploadId,
                    'chunk_index' => $chunkIndex,
                    'done' => false
                ]);
            }
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowExt = $this->normalizeExt(config('sys.upload.file_ext'));
        if (!empty($allowExt) && !in_array($extension, $allowExt)) {
            $this->clearChunkDir($chunkDir);
            $this->error(403, 'File extension is not allowed', 'FILE_LIMIT');
        }

        $maxSize = (int) config('sys.upload.file_size') * 1024;
        if (!empty($maxSize) && $fileSize > $maxSize) {
            $this->clearChunkDir($chunkDir);
            $this->error(403, 'File is too large', 'FILE_LIMIT');
        }

        $dirname = date('Ymd');
        $uploadRoot = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        $targetDir = $uploadRoot . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $dirname;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $saveName = md5(uniqid((string) microtime(true), true)) . ($extension ? '.' . $extension : '');
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $saveName;
        $target = fopen($targetPath, 'ab');

        for ($i = 0; $i < $chunkTotal; $i++) {
            $partPath = $chunkDir . DIRECTORY_SEPARATOR . $i . '.part';
            $part = fopen($partPath, 'rb');
            stream_copy_to_stream($part, $target);
            fclose($part);
        }
        fclose($target);

        $realSize = filesize($targetPath);
        if (!empty($maxSize) && $realSize > $maxSize) {
            @unlink($targetPath);
            $this->clearChunkDir($chunkDir);
            $this->error(403, 'File is too large', 'FILE_LIMIT');
        }

        $relativePath = '/uploads/files/' . $dirname . '/' . $saveName;
        $imageSize = @getimagesize($targetPath);
        $fileInfo = [
            'key' => uniqid(),
            'path' => $relativePath,
            'name' => $fileName,
            'extension' => $extension,
            'mime' => $fileMime ?: (mime_content_type($targetPath) ?: ''),
            'size' => $realSize,
            'md5' => md5_file($targetPath),
            'sha1' => sha1_file($targetPath),
            'width' => $imageSize ? $imageSize[0] : null,
            'height' => $imageSize ? $imageSize[1] : null,
            'is_image' => $imageSize ? 1 : 0,
            'app' => app('http')->getName()
        ];

        $file = FileModel::create($fileInfo);
        $this->clearChunkDir($chunkDir);

        $this->success(201, $file);
    }

    protected function normalizeExt($ext)
    {
        if (is_string($ext)) {
            $ext = $ext ? explode(',', $ext) : [];
        }

        if (!is_array($ext)) {
            return [];
        }

        return array_map(function ($item) {
            return strtolower(trim($item));
        }, array_filter($ext));
    }

    protected function clearChunkDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    protected function createThumb($path = '', $width = 0, $height = 0, $quality = 75, $fileExt = 'jpg')
    {
        $phyPath = app()->getRootPath() . 'public' . $path;

        if (!file_exists($phyPath)) {
            return false;
        }

        $fileDir = pathinfo($phyPath, PATHINFO_DIRNAME);

        $fileName = md5(microtime(true)) . '.' . $fileExt;

        $imageInfo = \think\Image::open($phyPath);

        $imgWidth = $imageInfo->width();

        $imgHeight = $imageInfo->height();

        // 定宽等比缩放
        if ($width > 0 && $height == 0) {
            $height = (int) $imgHeight / $imgWidth * $width;
        } // 定高等比缩放
        else if ($height > 0 && $width == 0) {
            $width = (int) $imgWidth / $imgHeight * $height;
        }

        \think\Image::open($phyPath)->thumb($width, $height)->save($fileDir . DIRECTORY_SEPARATOR . $fileName, $fileExt, $quality);

        $dir = substr($fileDir, strripos($fileDir, '/'));

        unlink($phyPath);

        return '/uploads/images' . $dir . '/' . $fileName;
    }

    public function delete($key)
    {
        $file = FileModel::where('key', $key)->find();

        $filename = public_path() . $file->getData('path');

        @unlink($filename);

        $file->delete();

        $this->success(204);
    }
}
