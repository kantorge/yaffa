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
		if ($request->get('q')) {
            $accounts = $this->account
                ->select(['id', 'name AS text'])
                ->where('name', 'LIKE', $request->get('q') . '%')
                ->where('active', 1)
                ->orderBy('name')
                ->take(10)
                ->get();
		} else {
            $categories = $this->category
                ->where('active', 1)
                ->get();
            $categories->sortBy('full_name');

        }

        $subset = $categories->take(10)->map(function ($category) {
            return $category->only(['id', 'full_name']);
        });

        //return data
        return response()
            ->json($subset,
                Response::HTTP_OK);
    }
}


/*
FIN

$json = [];

		if(!empty($this->input->get("q"))) {
            if ($this->input->get("active") == "1") {
                $this->db->where('active', 1);
            }
			$this->db->like('text', $this->input->get("q"));
			$this->db->order_by("text", "asc");
			$query = $this->db->select('id, text')
						->limit(10)
						->get("category_full_list");

			$json = $query->result();
		} else {
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
            $json = $this->db->query($query)->result();
        }


		echo json_encode($json);

*/
