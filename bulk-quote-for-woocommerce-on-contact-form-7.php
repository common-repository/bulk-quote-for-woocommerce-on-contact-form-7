<?php

/*
Plugin Name: Woocommerce bulk quote on contact form 7
Plugin URI: http://www.dubbofeng.com/wordpress-plugins
Description: Add Woocommerce bulk quote fields to the popular Contact Form 7 plugin.
Author: Dubbo web studio. 
Author URI: http://www.dubbofeng.com/
Version: 1.2
*/

add_action('plugins_loaded', 'contact_form_7_woo_bulk_quote_fields', 10); 
function contact_form_7_woo_bulk_quote_fields() {
  global $pagenow;
  if(!function_exists('wpcf7_add_form_tag') || !class_exists( 'WooCommerce' )) {
    if($pagenow != 'plugins.php') { return; }
    add_action('admin_notices', 'cf_woo_bulk_quote_fields_error');
    add_action('admin_enqueue_scripts', 'contact_form_7_woo_bulk_quote_fields_scripts');

    function cf_woo_bulk_quote_fields_error() {
      $out = '<div class="error" id="messages"><p>';
      $out .= 'The Contact Form 7 plugin and WooCommerce plugin must be installed and activated for the WooCommerce bulk quote plugin to work. <a href="'.admin_url('plugin-install.php?tab=plugin-information&plugin=contact-form-7&from=plugins&TB_iframe=true&width=600&height=550').'" class="thickbox" title="Contact Form 7">Install Contact Form 7.</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <a href="'.admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce&from=plugins&TB_iframe=true&width=600&height=550').'" class="thickbox" title="Contact Form 7">Install WooCommerce.</a>';
      $out .= '</p></div>';
      echo $out;
    }
  }
}

function woocommerce_subcats_from_parentcat_by_ID($parent_cat_ID) {
    $args = array(
       'hierarchical' => 1,
       'show_option_none' => '',
       'hide_empty' => 0,
       'parent' => $parent_cat_ID,
       'taxonomy' => 'product_cat'
    );
  $subcats = get_categories($args);
  return $subcats;
}

function contact_form_7_woo_bulk_quote_fields_scripts() {
  wp_enqueue_script('thickbox');
}

add_action( 'wpcf7_init', 'custom_add_shortcode_woo_bulk_quote' );

function custom_add_shortcode_woo_bulk_quote() {
  wpcf7_add_form_tag( 'woo_bulk_quote', 'custom_woo_bulk_quote_shortcode_handler', true );
}

function custom_woo_bulk_quote_shortcode_handler( $tag ) {

  if ( is_array( $tag ) ){
    $type = $tag['type'];
    $name = $raw_name = $tag['name'];
    $options = (array) $tag['options'];
    $values = (array) $tag['values'];
  }else if ( is_object( $tag ) ){
    $type = $tag->type;
    $name = $raw_name = $tag->name;
    $options = (array) $tag->options;
    $values = (array) $tag->values;
  }else{
    return '';
  }
  if ( empty( $name ) ) {
    return '';
  }
  $pa = '';
  foreach ( $options as $option ) {
    if ( preg_match( '%^id:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
      $id_att = $matches[1];
    } elseif ( preg_match( '%^class:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
      $class_att .= ' ' . $matches[1];
    } elseif ( preg_match( '%^category:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
      $category_att = $matches[1];
    } elseif ( preg_match( '%^columns:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
      $columns_att = $matches[1];
    } elseif ( preg_match( '%^pa:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
      $pa = $matches[1];
    }
  }

  if(!isset($category_att)){
    $category_att = 1;
  }
  if(!isset($columns_att) || wp_is_mobile()){
    $columns_att = 1;
  }

  if ( $class_att ){
    $atts .= ' class="' . trim( $class_att ) . '"';
  }
  if ( $id_att ) {
    $id_att = trim( $id_att );
    $atts .= ' id="' . trim( $id_att ) . '"';
  }else{ 
    // we need an ID for the script to work!
    $id_att = "woo_bulk_quote".rand(1000,9999);
    $atts .= ' id="' . $id_att . '"'; 
  }
  $all_categories = woocommerce_subcats_from_parentcat_by_ID($category_att);
  $cat_ids = array($category_att);

  foreach ($all_categories as $cat){
       array_push($cat_ids, $cat->term_id);
  }
  $args = array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'tax_query' => array(
      array(
            'taxonomy'  => 'product_cat',
            'field'     => 'id', 
            'terms'     => $cat_ids
      )
    )
  );
   $html  = '
  <input type="hidden" name="'.$name.'" id="'.trim( $id_att ).'" value="">
  <table id="'.trim( $id_att ).'field" border="1" width="100%" cellpadding="5">';
  $loop = new WP_Query( $args );
  if ( $loop->have_posts() ) {
    $n = 0;
    while ( $loop->have_posts() ) : $loop->the_post();
      global $product;
      if($pa){
        $pavs = get_the_terms( $product->id, $pa);
      }
      if($pa && !empty($pavs)){  
        foreach ($pavs as $pk => $pav) {
          if($n % $columns_att == 0){
            $html  .= '<tr>';
          }
          $html  .= '<td>'.$product->get_sku().'</td><td><a href="'.get_permalink().'" target="_blank">'.get_the_title()." | <i>".$pav->name.'</i></a></td><td><input size="5" name="product_'.get_the_ID().'_'.$pk.'" data-id="'.get_the_ID().'_'.$pk.'" value="0" type="numeric" /></td>';
          $n++;
          if($n % $columns_att == 0){
            $html  .= '</tr>';
          }
        }
      }else{
        if($n % $columns_att == 0){
          $html  .= '<tr>';
        }
        $html  .= '<td>'.$product->get_sku().'</td><td><a href="'.get_permalink().'" target="_blank">'.get_the_title().'</a></td><td><input size="5" name="product_'.get_the_ID().'" data-id="'.get_the_ID().'" value="0" type="numeric" /></td>';
        $n++;
        if($n % $columns_att == 0){
          $html  .= '</tr>';
        }
      }
      
    endwhile;
    if($n % $columns_att != 0){
      $td_left = ($columns_att-($n % $columns_att))*3;
      for ($i=0; $i < $td_left; $i++) { 
        $html .= '<td></td>';
      }
      $html  .= '</tr>';
    }
  } else {
    echo __( 'No products found' );
  }
  wp_reset_postdata();
  
  $html  .= "</table>";
  $html .= '<script>
      jQuery(document).ready(function($) {
        //var products = {};
        var productsText = "";
        var updateProducts = function(){
         // products = {};
          productsText = "";
          $("#'.trim( $id_att ).'field td input").each(function(index, el) {
            if($(this).val() > 0){
              var productName = $(this).parent("td").prev("td").children("a").html();
              var productSKU = $(this).parent("td").prev("td").prev("td").html();
              var productId = $(this).data("id");
              var product = {"id" : productId, "sku": productSKU, "name": productName};
              //products.push(product);
              productsText += "id: "+productId+" | sku: "+productSKU+" | name: "+productName+ " | quantity: "+$(this).val() + "\\r\\n";
            }
          });
          return productsText;
        }
        $("#'.trim( $id_att ).'field td input").change(function(event) {
          var inputtext = updateProducts();
          $("#'.trim( $id_att ).'").val(inputtext);
        });
      });
    </script>';
  return $html;
}

add_action( 'admin_init', 'wpcf7_add_tag_generator_woo_bulk_quote', 30 );

function wpcf7_add_tag_generator_woo_bulk_quote() {
  if(function_exists('wpcf7_add_tag_generator')) {
    wpcf7_add_tag_generator( 'woo_bulk_quote', __( 'Woo bulk quote field', 'wpcf7' ), 'wpcf7-tg-pane-woo_bulk_quote', 'wpcf7_tg_pane_woo_bulk_quote' );
  }
}

function wpcf7_tg_pane_woo_bulk_quote() {
  ?>
  <div id="wpcf7-tg-pane-woo_bulk_quote" class="hidden">
  <form action="">
      <table>
        <tr>
          <td><?php echo esc_html( __( 'Name', 'wpcf7' ) ); ?><br /><input type="text" name="name" class="tg-name oneline" />
          </td>
          <td>
          </td>
        </tr>

        <tr>
          <td><code>id</code> (<?php echo esc_html( __( 'optional', 'wpcf7' ) ); ?>)<br />
            <input type="text" name="id" class="idvalue oneline option" />
          </td>
          <td><code>class</code> (<?php echo esc_html( __( 'optional', 'wpcf7' ) ); ?>)<br />
            <input type="text" name="class" class="classvalue oneline option" />
          </td>
        </tr>
        <tr>
          <td><code>category id</code> (<?php echo esc_html( __( 'optional', 'wpcf7' ) ); ?>)<br>
            <input type="hidden" value="" name="category" id="woo_bulk_quote_category_input" class="option" />
            <select name="category_id" id="woo_bulk_quote_category_select" class="oneline option">
            <?php
              $taxonomy     = 'product_cat';
              $orderby      = 'name';  
              $show_count   = 0;      // 1 for yes, 0 for no
              $pad_counts   = 0;      // 1 for yes, 0 for no
              $hierarchical = 1;      // 1 for yes, 0 for no  
              $title        = '';  
              $empty        = 0;

              $args = array(
               'taxonomy'     => $taxonomy,
               'orderby'      => $orderby,
               'show_count'   => $show_count,
               'pad_counts'   => $pad_counts,
               'hierarchical' => $hierarchical,
               'title_li'     => $title,
               'hide_empty'   => $empty
               );
              $all_categories = get_categories( $args );
              foreach ($all_categories as $cat) {
                if($cat->category_parent == 0) {
                  $category_id = $cat->term_id;       
                  echo '<option value="'.$category_id.'">'. $cat->name .'</option>';
                  $args2 = array(
                    'taxonomy'     => $taxonomy,
                    'child_of'     => 0,
                    'parent'       => $category_id,
                    'orderby'      => $orderby,
                    'show_count'   => $show_count,
                    'pad_counts'   => $pad_counts,
                    'hierarchical' => $hierarchical,
                    'title_li'     => $title,
                    'hide_empty'   => $empty
                    );
                  $sub_cats = get_categories( $args2 );
                  if($sub_cats) {
                    foreach($sub_cats as $sub_category) {
                      echo '<option value="'.$sub_category->term_id.'">--'. $sub_category->name .'</option>';
                    }   
                  }
                }       
            }?>
            </select>
            <script>
              jQuery(document).ready(function($) {
                function woo_bulk_quote_category_input_change(){
                  $("#woo_bulk_quote_category_input").val($("#woo_bulk_quote_category_select").val()).trigger('change');
                }
                $(document.body).on('change','#woo_bulk_quote_category_select', function(event) {
                  woo_bulk_quote_category_input_change();
                });
              });
            </script>
          </td>
          <td><code>Columns</code> (<?php echo esc_html( __( 'optional', 'wpcf7' ) ); ?>)<br>
            <input type="number" name="columns" class="numeric oneline option" min="1" max="6"
            >
          </td>
        </tr>
        <tr>
          <td><code>product attribute</code> (<?php echo esc_html( __( 'optional', 'wpcf7' ) ); ?>)<br>
            <input type="hidden" value="" name="pa" id="woo_bulk_quote_pa_input" class="option" />
            <select name="pa_name" id="woo_bulk_quote_pa_select" class="oneline option">
              <option value="">-- select --</option>
            <?php
             $attribute_taxonomies = wc_get_attribute_taxonomies();
              foreach ($attribute_taxonomies as $tk => $term) {
                echo '<option value="pa_'.$term->attribute_name.'">'.$term->attribute_label .'</option>';
              }
            ?>
            </select>
            <script>
              jQuery(document).ready(function($) {
                function woo_bulk_quote_pa_input_change(){
                  $("#woo_bulk_quote_pa_input").val($("#woo_bulk_quote_pa_select").val()).trigger('change');
                }
                $(document.body).on('change','#woo_bulk_quote_pa_select', function(event) {
                  woo_bulk_quote_pa_input_change();
                });
              });
            </script>
          </td>
          <td>
          </td>
        </tr>
      </table>

      <div class="tg-tag"><?php echo esc_html( __( "Copy this code and paste it into the form left.", 'wpcf7' ) ); ?><br />
        <input type="text" name="woo_bulk_quote" class="tag" readonly="readonly" onfocus="this.select()" />
      </div>

      <div class="tg-mail-tag"><?php echo esc_html( __( "And, put this code into the Mail fields below.", 'wpcf7' ) ); ?><br />
        <input type="text" class="mail-tag" readonly="readonly" onfocus="this.select()" />
      </div>
    </form>
  </div>
<?php
}

?>