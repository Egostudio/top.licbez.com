<?php

require_once('Okay.php');

class Brands extends Okay {

    /*Выбираем все бренды*/
    public function get_brands($filter = array()) {
        $limit = 100;
        $page = 1;
        $category_id_filter = '';
        $category_join = '';
        $visible_filter = '';
        $product_id_filter = '';
        $product_join = '';
        $visible_brand_filter = '';
        $features_filter = '';

        if(isset($filter['limit'])) {
            $limit = max(1, intval($filter['limit']));
        }

        if(isset($filter['page'])) {
            $page = max(1, intval($filter['page']));
        }

        $sql_limit = $this->db->placehold(' LIMIT ?, ? ', ($page-1)*$limit, $limit);

        if(isset($filter['visible'])) {
            $visible_filter = $this->db->placehold('AND p.visible=?', intval($filter['visible']));
        }

        if(isset($filter['visible_brand'])) {
            $visible_brand_filter = $this->db->placehold('AND b.visible=?', intval($filter['visible_brand']));
        }

        if(isset($filter['product_id'])) {
            $product_id_filter = $this->db->placehold('AND p.id in (?@)', (array)$filter['product_id']);
            $product_join = $this->db->placehold("LEFT JOIN __products p ON p.brand_id=b.id");
        }

        if(!empty($filter['category_id'])) {
            $category_join = $this->db->placehold("LEFT JOIN __products p ON p.brand_id=b.id LEFT JOIN __products_categories pc ON p.id = pc.product_id");
            $category_id_filter = $this->db->placehold("AND pc.category_id in(?@) $visible_filter", (array)$filter['category_id']);
        }

        if(!empty($filter['features'])) {
            foreach($filter['features'] as $feature=>$value) {
                $features_filter .= $this->db->placehold('AND p.id in (SELECT product_id FROM __options WHERE feature_id=? AND translit in(?@) ) ', $feature, (array)$value);
            }
            if (empty($category_join)) {
                $features_filter .= $visible_filter;
                $category_join = $this->db->placehold("LEFT JOIN __products p ON (p.brand_id=b.id)");
            }
        }


        $lang_sql = $this->languages->get_query(array('object'=>'brand'));
        // Выбираем все бренды
        $query = $this->db->placehold("SELECT 
                DISTINCT b.id, 
                b.url, 
                b.image, 
                b.last_modify,
                b.visible,
                b.position,
                (SELECT count(p1.id) FROM __products p1 LEFT JOIN __products_categories pc1 ON p1.id = pc1.product_id WHERE p1.brand_id = b.id AND pc.category_id=pc1.category_id) count_products,
                $lang_sql->fields 
            FROM __brands b
            $lang_sql->join
            $category_join
            $product_join
            WHERE 
                1 
                $category_id_filter
                $features_filter
                $visible_brand_filter
                $product_id_filter
            ORDER BY b.position
            $sql_limit
        ");
//        echo $query;

        $this->db->query($query);
        return $this->db->results();
    }

    public function count_brands($filter = array()) {
        $category_id_filter = '';
        $category_join = '';
        $visible_filter = '';
        $product_id_filter = '';
        $product_join = '';
        $visible_brand_filter = '';
        $features_filter = '';

        if(isset($filter['visible'])) {
            $visible_filter = $this->db->placehold('AND p.visible=?', intval($filter['visible']));
        }

        if(isset($filter['visible_brand'])) {
            $visible_brand_filter = $this->db->placehold('AND b.visible=?', intval($filter['visible_brand']));
        }

        if(isset($filter['product_id'])) {
            $product_id_filter = $this->db->placehold('AND p.id in (?@)', (array)$filter['product_id']);
            $product_join = $this->db->placehold("LEFT JOIN __products p ON p.brand_id=b.id");
        }

        if(!empty($filter['category_id'])) {
            $category_join = $this->db->placehold("LEFT JOIN __products p ON p.brand_id=b.id LEFT JOIN __products_categories pc ON p.id = pc.product_id");
            $category_id_filter = $this->db->placehold("AND pc.category_id in(?@) $visible_filter", (array)$filter['category_id']);
        }

        if(!empty($filter['features'])) {
            foreach($filter['features'] as $feature=>$value) {
                $features_filter .= $this->db->placehold('AND p.id in (SELECT product_id FROM __options WHERE feature_id=? AND translit in(?@) ) ', $feature, (array)$value);
            }
            if (empty($category_join)) {
                $features_filter .= $visible_filter;
                $category_join = $this->db->placehold("LEFT JOIN __products p ON (p.brand_id=b.id)");
            }
        }

        $lang_sql = $this->languages->get_query(array('object'=>'brand'));
        // Выбираем все бренды
        $query = $this->db->placehold("SELECT
                count(distinct b.id) as count
            FROM __brands b
            $lang_sql->join
            $category_join
            $product_join
            WHERE
                1
                $category_id_filter
                $features_filter
                $visible_brand_filter
                $product_id_filter
        ");
        $this->db->query($query);
        return $this->db->result('count');
    }

    /*Выбираем конкретный бренд*/
    public function get_brand($id,$visible=0) {
        if (empty($id)) {
            return false;
        }
        $visible = $visible==1 ? 'AND b.visible=1 ' : '';
        if(is_int($id)) {
            $filter = $this->db->placehold($visible . 'AND b.id = ?', intval($id));
        } else {
            $filter = $this->db->placehold($visible . 'AND b.url = ?', $id);
        }
        
        $lang_sql = $this->languages->get_query(array('object'=>'brand'));
        $query = "SELECT 
                b.id, 
                b.url, 
                b.image, 
                b.last_modify,
                b.visible,
                b.position,
                $lang_sql->fields                
            FROM __brands b
            $lang_sql->join
            WHERE 
                1 
                $filter
            LIMIT 1
        ";
        
        $this->db->query($query);
        return $this->db->result();
    }

    /*Добавление бренда*/
    public function add_brand($brand) {

        $name = $brand['name'];

        $brand = (object)$brand;
        $brand->url = preg_replace("/[\s]+/ui", '', $brand->url);
        $brand->url = strtolower(preg_replace("/[^0-9a-z]+/ui", '', $brand->url));
        if(empty($brand->url)) {
            $brand->url = $this->translit_alpha($brand->name);
        }
        while($this->get_brand((string)$brand->url)) {
            if(preg_match('/(.+)([0-9]+)$/', $brand->url, $parts)) {
                $brand->url = $parts[1].''.($parts[2]+1);
            } else {
                $brand->url = $brand->url.'2';
            }
        }
        
        // Проверяем есть ли мультиязычность и забираем описания для перевода
        $result = $this->languages->get_description($brand, 'brand');
       
//$message = $brand;
//mail('iv.savchuk@gmail.com', 'the subject', $message);
        $brand->last_modify = date("Y-m-d H:i:s");
        $this->db->query("INSERT INTO __brands SET ?%", $brand);
        $id = $this->db->insert_id();
        $this->db->query("UPDATE __brands SET position=id WHERE id=? LIMIT 1", $id);

//savchuk
        $this->db->query("UPDATE __brands SET name='" . $name . "' WHERE id=? LIMIT 1", $id, '889');
       
        // Если есть описание для перевода. Указываем язык для обновления
        if(!empty($result->description)) {
            $this->languages->action_description($id, $result->description, 'brand');
        }
        return $id;
    }

    /*Обновление бренда*/
    public function update_brand($id, $brand) {
        $brand = (object)$brand;
        // Проверяем есть ли мультиязычность и забираем описания для перевода
        $result = $this->languages->get_description($brand, 'brand');
        
        $brand->last_modify = date("Y-m-d H:i:s");
        $query = $this->db->placehold("UPDATE __brands SET ?% WHERE id=? LIMIT 1", $brand, intval($id));
        $this->db->query($query);
        

        
        // Если есть описание для перевода. Указываем язык для обновления
        if(!empty($result->description)) {
            $this->languages->action_description($id, $result->description, 'brand', $this->languages->lang_id());
        }
        
        return $id;
    }

    /*Удаление бренда*/
    public function delete_brand($id) {
        if(!empty($id)) {
            $this->image->delete_image($id, 'image', 'brands', $this->config->original_brands_dir, $this->config->resized_brands_dir);
            $query = $this->db->placehold("DELETE FROM __brands WHERE id=? LIMIT 1", $id);
            $this->db->query($query);
            $query = $this->db->placehold("UPDATE __products SET brand_id=NULL WHERE brand_id=?", $id);
            $this->db->query($query);
            $this->db->query("DELETE FROM __lang_brands WHERE brand_id=?", $id);
        }
    }
    
}
