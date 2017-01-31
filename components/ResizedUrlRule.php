<?php

namespace mgcode\image\components;

use yii\base\Object;
use yii\web\UrlRuleInterface;

/**
 * ResizedUrlRule used for request parsing and sending to image resize action.
 * Simply add url rule to urlManager configuration as follows:
 * ```php
 * 'rules' => [
 *     ['class' => '\mgcode\image\components\ResizedUrlRule'],
 *     // ...
 * ]
 * ```
 * @author Maris Graudins <maris@mg-interactive.lv>
 */
class ResizedUrlRule extends Object implements UrlRuleInterface
{
    /** @inheritdoc */
    public function createUrl($manager, $route, $params)
    {
        return false;
    }

    /** @inheritdoc */
    public function parseRequest($manager, $request)
    {
        $component = \Yii::$app->image;
        $prefix = $component->urlPrefix;

        $pathInfo = $request->getPathInfo();
        if ($prefix === '' || strpos($pathInfo.'/', $prefix.'/') === 0) {
            // Remove prefix from path
            $path = ltrim($pathInfo, $prefix.'/');

            // Divide url and validate
            $explode = explode('/', $path);
            if (count($explode) < 4) {
                return false;
            }

            // Extract parameters
            $params = [];
            $params['fileName'] = array_pop($explode);
            $params['imageHash'] = array_pop($explode);
            $params['type'] = array_shift($explode);
            $params['path'] = implode('/', $explode);
            $params['id'] = $this->parseImgId($params['fileName']);

            return [$component->urlRoute, $params];
        } else {
            return false;
        }
    }

    /**
     * Parses image id from string
     * @param string $str
     * @return mixed
     */
    public function parseImgId($str)
    {
        $explode = explode('.', $str);
        return (int) $explode[0];
    }
}