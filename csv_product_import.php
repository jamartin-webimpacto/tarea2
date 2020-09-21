<?php

require(dirname(__FILE__).'/config/config.inc.php');

echo "<pre>";
//borrar();exit;

//Obteniendo ids lenguages activos
foreach(Language::getLanguages(true) as $language){
    $langs[] = $language['id_lang'];
}

//Importando datos del fichero a array                 
$csv = 'products.csv';    
$fp = fopen($csv,"r");
while ($data[] = fgetcsv ($fp, 1000, ",")) {
}
fclose($fp);

//Eliminamos el ultimo elemento del array que estará vacío
array_pop($data);
//dump($data);exit;

$numProducts = count($data)-1;

echo "<h3>Importando ".$numProducts." en Prestashop.......</h3><br/><br/><br/>";

//Inicio importacion
for($i=1; $i<=$numProducts;$i++){
    //Preparando el array product->name
    foreach($langs as $lang){
        $names[$lang] = $data[$i][0];
    }
    $product = new Product();
    $product->name = $names;
    $product->reference = $data[$i][1];
    $product->ean13 = $data[$i][2];
    $product->wholesale_price = (float)$data[$i][3];
    $product->price = (float)$data[$i][4];
    $product->redirect_type ='301-category';
    $impuestos = floatval($data[$i][5]);//.'.000');
    $product->id_tax_rules_group = (int)getIdTax($impuestos);
    $product->on_sale = 0;
    $product->id_manufacturer = (int)getIdMarcaProducto($data[$i][8]);  
    $product->add();
    StockAvailable::setQuantity($product->id, $product->id, (int)$data[$i][6]);
            
    //Obteniendo los id de las categorias y creando las necesarias
    $categories = explode(';',$data[$i][7]);
    $defaultCategory = 0;
    $idCategories = [];
    foreach($categories as $category){
        $idCategory = getIdProductCategory(trim($category), $langs);
        if($idCategory){
            if($defaultCategory == 0){
                $product->id_category_default = $idCategory;
            }
            $idCategories[]=$idCategory;  
            $defaultCategory++;
        }
    }
    if(count($idCategories)>0){
        $product->addToCategories($idCategories);
	//$shops = Shop::getShops(true, null, true);    
    }
    dump($product);
}

echo "Datos actualizados <br/> $numProducts productos insertados  <br/>";

/**
* Conseguir el grupo de id_tax al que pertenece un impuesto
*/
function getIdTax($tax){
    $db = \Db::getInstance();
    $request = "SELECT id_tax "
            . "FROM ps_tax "
            . "WHERE rate = $tax ";
    $id_tax = $db->getValue($request);
    return $id_tax;  
}

/**
* Obtener category_id si existe, sino la crea y devuelve el id
*/
function getIdProductCategory($name,$lang){   
    $result = buscarCategoriaPorNombre(Configuration::get('PS_LANG_DEFAULT'), $name);
    dump($result);
    if($result){  
        return $result[0]['id_category'];
    }
    else{
        return insertarCategoriaDB(pSQL($name),$lang);   
    }   
}

/*
 * Crear categoría
 */
function insertarCategoriaDB($name,$langs){
    
    $newCategory = new \Category();
    $newCategory->active = 1;
    foreach($langs as $language){
        $names[(int)$language]=$name;
        $links_rewriters[(int)$language]= seo_url($name);
    }
    $newCategory->name= $names;
    $newCategory->id_parent = 2;
    $newCategory->position = 1;
    $newCategory->description = '';
    $newCategory->is_root_category =1;
    $newCategory->meta_keywords = '';
    $newCategory->meta_description = '';
    $newCategory->link_rewrite = $links_rewriters;
    
    $id = creaCategoriaBd((int)\Context::getContext()->shop->id);

    $newCategory->id_category = $id;
    $newCategory->id = $id;
    $newCategory->update();
    dump($newCategory);
  
    return $id;
}

/*
 * Crea una tupla en BD de categoria
 */
function creaCategoriaBd($shopId){
    $db = \Db::getInstance();

    $db->insert('category',array(
        'id_parent' => 1,
        'id_shop_default' => $shopId,
        'active' => 1,
        'is_root_category' => 1,
        'date_add' => date('Y-m-d H:i:s'),
    ));
    $id = Db::getInstance()->Insert_ID();
    
    return $id;
}

function buscarCategoriaPorNombre($lang,$name){
    $db = \Db::getInstance();
    $request = "SELECT id_category 
            FROM ps_category_lang
            WHERE  name = '".$name."' AND id_lang = ".$lang; 
    $id = $db->executeS($request);
    //dump($id);
    return $id;
}

/**
* Obtener marca o instanciar una nueva si no existe
*/
function getIdMarcaProducto($nombreMarca){
    if($id = \Manufacturer::getIdByName($nombreMarca)){
        return $id;
    }
    else{
       $db = \Db::getInstance();
       $db->insert('manufacturer',array(
           'name' => $nombreMarca,
           'date_upd' => date('Y-m-d H:i:s'),
       ));
       getIdMarcaProducto($nombreMarca);
    }  
}

/*
 * Convierte a url amigable el string para link_rewrite
 */
function seo_url($cadena){
    $cadena = utf8_decode($cadena);
    $cadena = str_replace(' ', '-', $cadena);
    $cadena = str_replace(',', '', $cadena);
    $cadena = str_replace('?', '', $cadena);
    $cadena = str_replace('+', '', $cadena);
    $cadena = str_replace(':', '', $cadena);
    $cadena = str_replace('??', '', $cadena);
    $cadena = str_replace('`', '', $cadena);
    $cadena = str_replace('!', '', $cadena);
    $cadena = str_replace('¿', '', $cadena);
    $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿ??';
    $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    $cadena = strtr($cadena, utf8_decode($originales), $modificadas);
    echo"<br/> Seo Categoria ".$cadena."<br/>";
    return $cadena;
}

/*
 * Funcion usada en desarrollo para eliminar de las tablas los registros
 * creados por el script cuando hay errores
 */
function borrar(){
    echo "Borrando....";
    $db = \Db::getInstance();
    $request = "DELETE FROM ps_product "
            . " WHERE id_product > 19";
    $db->execute($request);
    
    $request = "DELETE FROM ps_product_lang "
            . " WHERE id_product > 19";
    $db->execute($request);
    
    $request = "DELETE FROM ps_product_shop "
            . " WHERE id_product > 15";
    $db->execute($request);
    
    $request = "DELETE FROM ps_category"
            . " WHERE id_category > 15";
    $db->execute($request);
    
    $request = "DELETE FROM ps_category_lang"
            . " WHERE id_category > 15";
    $db->execute($request);
    
    $request = "DELETE FROM ps_category_shop"
            . " WHERE id_category > 15";
    $db->execute($request);
    
    $request = "DELETE FROM ps_category_product"
            . " WHERE id_product > 22";
    $db->execute($request);
    
    echo "Borrado";
}








