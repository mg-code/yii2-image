<?php
namespace mgcode\image\controllers;

use Yii;
use yii\caching\Cache;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ImageController extends Controller
{
    public $cacheComponent = 'cache';

    public function init()
    {
        parent::init();
        \Yii::$app->errorHandler->errorAction = '/image/error';
    }

    public function actionResize($fileName, $imageHash, $type, $path)
    {
        $component = \Yii::$app->image;
        $type = (int) $type;
        $fileName = (string) $fileName;
        $imageHash = (string) $imageHash;
        $path = (string) $path;

        // Validate request
        if (!$component->types->exist($type) || $imageHash != $component->getUrlHash($type, $path, $fileName)) {
            throw new NotFoundHttpException();
        }
        $originalFile = $component->getOriginalFile($path, $fileName);
        if (!file_exists($originalFile['fullPath'])) {
            throw new NotFoundHttpException();
        }

        // Waits for resize for 20 seconds in case if image is already resizing.
        $destination = $component->getResizeDestination($type, $path, $fileName);
        for ($i = 1; $i <= 20; $i++) {
            if (!$this->isResizing($type, $path, $fileName)) {
                break;
            }
            if (file_exists($destination)) {
                $this->serveImage($destination);
                return;
            }
            usleep(1 * 100000);
        }

        $this->resizeImage($type, $path, $fileName);
        $this->serveImage($destination);
    }

    public function actionError()
    {
        if (($exception = \Yii::$app->getErrorHandler()->exception) === null) {
            return '';
        }
        if ($exception instanceof HttpException) {
            $msg = $exception->statusCode;
        } else {
            $msg = $exception->getCode();
        }
        if (YII_DEBUG) {
            $msg .= ' '.$exception->getMessage();
        }
        return $msg;
    }

    /**
     * Serves image
     * @param string $resizePath
     */
    protected function serveImage($resizePath)
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        header("Content-Type: ".FileHelper::getMimeType($resizePath));
        header("Content-Length: ".filesize($resizePath));
        readfile($resizePath);
    }

    protected function resizeImage($type, $path, $fileName)
    {
        /** @var Cache $cache */
        $cache = \Yii::$app->get($this->cacheComponent);
        $cache->set($this->getCacheKey($type, $path, $fileName), true, 20);
        
        $component = \Yii::$app->image;
        $component->resizeImage($type, $path, $fileName);
    }

    /**
     * Checks if image currently is resizing
     * @param $type
     * @param $path
     * @param $fileName
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    protected function isResizing($type, $path, $fileName)
    {
        /** @var Cache $cache */
        $cache = \Yii::$app->get($this->cacheComponent);
        if ($cache->get($this->getCacheKey($type, $path, $fileName))) {
            return true;
        }
        return false;
    }

    /**
     * Returns cache key for resizing
     * @param int $type
     * @param $path
     * @param $fileName
     * @return string
     */
    protected function getCacheKey($type, $path, $fileName)
    {
        return print_r([__METHOD__, $type, $path, $fileName], true);
    }
}