<?php
declare(strict_types = 1);

namespace Groove\Hubshoply\Model\RestOutput;

class DataHelper
{
    /**
     * @param $data
     */
    public function formatPricesRelatedData(&$data)
    {
        array_walk_recursive($data, function (&$item, $key) {
            if (preg_match('/amount|total|price|discount/', (string)$key) && is_numeric($item)) {
                $item = sprintf('%f', $item);
            }
        });
    }

    /**
     * @param $sourceKey
     * @param $targetKey
     * @param $source
     *
     * @return mixed
     */
    public function replaceKeys($sourceKey, $targetKey, &$source)
    {
        if (isset($source[$sourceKey])) {
            $source[$targetKey] = $source[$sourceKey];
            unset($source[$sourceKey]);
        }

        return $source;
    }

}
