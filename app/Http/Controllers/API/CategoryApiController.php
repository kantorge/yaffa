<?php

namespace App\Http\Controllers\API;

use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryApiController extends Controller
{
    protected $category;

    public function __construct(Category $category)
    {
        $this->category = $category;
    }

    public function getList(Request $request)
    {
		$query = $request->get('q');
        if ($query) {
            $categories = $this->category
                ->where('active', 1)
                ->get()
                ->filter(function ($category) use ($query) {
                    return stripos($category->full_name, $query) !== false;
                });
		} else {
            //TODO: sort by payee relevance
            $categories = $this->category
                ->where('active', 1)
                ->get();
        }

        $subset = $categories
            ->sortBy('full_name')
            ->take(10)
            ->map(function ($category) {
                $category->text = $category->full_name;
                return $category->only(['id', 'text']);
            })
            ->values();

        //return data
        return response()
            ->json(
                $subset,
                Response::HTTP_OK
            );
    }

    public function getItem(Category $category)
    {
        return response()
            ->json(
                $category,
                Response::HTTP_OK
            );
    }
}


/*
    $query = "SELECT    `ti`.`categories_id` AS `id`,
                        `c`.`text`
                FROM `transaction_items` AS `ti`
                LEFT JOIN `category_full_list` AS `c` ON `c`.`id` = `ti`.`categories_id`
                WHERE `ti`.`transaction_headers_id` IN (
                    SELECT `id`
                    FROM `standard_transaction_headers`
                    WHERE `payees_id` = ".$this->db->escape($this->input->get('payee'))."
                )
                ".($this->input->get("active") == "1" ? " AND `c`.`active` = 1 " : "")."
                GROUP BY `ti`.`categories_id`,
                        `c`.`text`
                ORDER BY count(`ti`.`id`)  DESC
                LIMIT 5";
*/
