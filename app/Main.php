<?php

namespace App;

use Carbon\Carbon;

class Main
{
    public static function getBodyFilteredAttribute($object)
    {
        $pattern = '/\[\[([0-9]+)\]\]/';
        $filteredBody = $object->body;

        if (preg_match_all($pattern, $filteredBody, $matches)) {
            foreach ($matches[1] as $match) {
                if ($image = Image::find($match)) {
                    $filteredBody = str_replace("[[$image->id]]", '<img src="'.$image->preset('medium').'" />', $filteredBody);
                }
            }
        }

        return nl2br($filteredBody);
    }

    public static function getExpireData($type, $withField = 0)
    {
        $names = ['daysFrom', 'daysTo'];
        $data = null;

        if (config("content_$type.index.expire.field")) {
            if ($withField == 1) {
                $data['field'] = config("content_$type.index.expire.field");
            }

            if (config("content_$type.index.expire.type") == 'date') {
                $format = 'Y-m-d';
            } else {
                $format = 'Y-m-d H:i:s';
            }

            foreach ($names as $name) {
                if (config("content_$type.index.expire.$name") && ! is_numeric(config("content_$type.index.expire.$name"))) {
                    $data[$name] = config("content_$type.index.expire.field");
                } elseif (config("content_$type.index.expire.$name")) {
                    $data[$name] = Carbon::now()->addDays(config("content_$type.index.expire.$name"))->format($format);
                } else {
                    $data[$name] = Carbon::now()->format($format);
                }
            }
        }

        return $data;
    }

    public static function sqlToArray($collection, $parent_id = 0)
    {
        $tree = [];

        foreach ($collection as $item) {
            if ($item['parent_id'] == $parent_id) {
                $children = self::sqlToArray($collection, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }

        return collect($tree);
    }

    public static function collectionAsSelect($tree, $indent = '', $eloquentCollection = [], $parameters = ['name' => 'name'], $saved = '', $level = 1)
    {
        if (count($eloquentCollection) && count($tree) == 0) {
            return self::collectionAsSelect(
                self::sqlToarray($eloquentCollection),
                $indent
            );
        } else {
            $items = [];

            if (count($tree)) {
                foreach ($tree as $item) {
                    $items[$item->id] = $saved.$item->{$parameters['name']};

                    if (isset($item->children) && count($item->children)) {
                        $items = $items +
                            self::collectionAsSelect(
                                $item->children,
                                $indent,
                                [],
                                $parameters,
                                $items[$item->id].$indent,
                                round((int) $level + 1)
                            );
                    }
                }
            }

            return $items;
        }
    }

    public static function getContentCollections($types)
    {
        $content_query = null;
        $i = 0;
        $viewVariables = [];

        foreach ($types as $key => $type) {
            ++$i;

            $query = null;

            if (isset($type['id'])) {
                //$query = Content::select(['*', DB::raw('\''.$key.'\' AS `pseudo`')])->where('id', $type['id'])->whereStatus($type['status']);
                $query = Content::where('id', $type['id'])->whereStatus($type['status']);
            } else {
                //$query = Content::select(['*', DB::raw('\''.$key.'\' AS `pseudo`')])->whereIn('type', $type['type'])->whereStatus($type['status']);
                $query = Content::whereIn('type', $type['type'])->whereStatus($type['status']);

                if (isset($type['whereBetween']) && ! empty($type['whereBetween'])) {
                    if (! isset($type['whereBetween']['only'])) {
                        $expireData = [
                            $type['whereBetween']['daysFrom'],
                            $type['whereBetween']['daysTo'],
                        ];

                        if (in_array($type['whereBetween']['field'], $expireData)) {
                            if (($datakey = array_search($type['whereBetween']['field'], $expireData)) !== false) {
                                unset($expireData[$datakey]);
                            }

                            $query = $query->whereRaw('`'.$type['whereBetween']['field'].'` >= ?', [
                                array_values($expireData)[0],
                            ]);
                        } else {
                            $query = $query->whereBetween($type['whereBetween']['field'], [
                                $type['whereBetween']['daysFrom'],
                                $type['whereBetween']['daysTo'],
                            ]);
                        }
                    } else {
                        $query = $query->whereRaw('IF(`type` = ?, ?, ?) BETWEEN ? AND ?', [
                            $type['whereBetween']['only'],
                            $type['whereBetween']['field'],
                            $type['whereBetween']['daysTo'],
                            $type['whereBetween']['daysFrom'],
                            $type['whereBetween']['daysTo'],
                        ]);
                    }
                }

                if (isset($type['with']) && $type['with'] !== null) {
                    $query = $query->with($type['with']);
                }

                if (isset($type['latest']) && $type['latest'] !== null) {
                    $query = $query->latest($type['latest']);
                }

                if (isset($type['skip']) && $type['skip'] !== null) {
                    $query = $query->skip($type['skip']);
                }

                if (isset($type['take']) && $type['take'] !== null) {
                    $query = $query->take($type['take']);
                }
            }

            /*if ($i == 1) {
                $content_query = $query;
            } else {
                $content_query = $content_query->unionAll($query);
            }*/
            $$key = $query->with('images')->get();
            $viewVariables[$key] = $$key;
        }

        //$content_query = $content_query->with('images')->get();

        /*foreach ($types as $key => $type) {
            $$key = Collection::make($content_query->where('pseudo', $key)->values()->all());

            $viewVariables[$key] = $$key;
        }*/

        return $viewVariables;
    }

    public static function getParentDestinations(array $collectionString, $viewVariables)
    {
        foreach ($collectionString as $type) {
            if (isset($viewVariables[$type])) {
                foreach ($viewVariables[$type] as $key => $element) {
                    $viewVariables[$type][$key]['destination'] = $element->destinations->first();
                    $viewVariables[$type][$key]['parent_destination'] = $element->getDestinationParent();
                }
            }
        }

        return $viewVariables;
    }
}
