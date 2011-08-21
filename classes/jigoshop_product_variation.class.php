<?php
/**
 * Product Variation Class
 * @class jigoshop_product_variation
 *
 * The JigoShop product variation class handles product variation data.
 *
 * @author 		Jigowatt
 * @category 	Classes
 * @package 	JigoShop
 */
class jigoshop_product_variation extends jigoshop_product {

	var $variation;
	var $variation_data;
	var $variation_id;
	var $variation_has_weight;
	var $variation_has_price;
	var $variation_has_sale_price;
	var $variation_has_stock;
	var $variation_has_sku;

	/**
	 * Loads all product data from custom fields
	 *
	 * @param   int		$id		ID of the product to load
	 */
	function jigoshop_product_variation( $variation_id ) {

		$this->variation_id = $variation_id;

		$product_custom_fields = get_post_custom( $this->variation_id );

		$this->variation_data = array();

		foreach ($product_custom_fields as $name => $value) :

			if (!strstr($name, 'tax_')) continue;

			$this->variation_data[$name] = $value[0];

		endforeach;

		$this->get_variation_post_data();

		/* Get main product data from parent */
		$this->id = $this->variation->post_parent;

		$parent_custom_fields = get_post_custom( $this->id );

		if (isset($parent_custom_fields['SKU'][0]) && !empty($parent_custom_fields['SKU'][0])) $this->sku = $parent_custom_fields['SKU'][0]; else $this->sku = $this->id;
		if (isset($parent_custom_fields['product_data'][0])) $this->data = maybe_unserialize( $parent_custom_fields['product_data'][0] ); else $this->data = '';
		if (isset($parent_custom_fields['product_attributes'][0])) $this->attributes = maybe_unserialize( $parent_custom_fields['product_attributes'][0] ); else $this->attributes = array();
		if (isset($parent_custom_fields['price'][0])) $this->price = $parent_custom_fields['price'][0]; else $this->price = 0;
		if (isset($parent_custom_fields['visibility'][0])) $this->visibility = $parent_custom_fields['visibility'][0]; else $this->visibility = 'hidden';
		if (isset($parent_custom_fields['stock'][0])) $this->stock = $parent_custom_fields['stock'][0]; else $this->stock = 0;

		// Again just in case, to fix WP bug
		$this->data = maybe_unserialize( $this->data );
		$this->attributes = maybe_unserialize( $this->attributes );
		$this->product_type = 'variable';

		if ($this->data) :
			$this->exists = true;
		else :
			$this->exists = false;
		endif;

		//parent::jigoshop_product( $this->variation->post_parent );

		/* Override parent data with variation */
		if (isset($product_custom_fields['SKU'][0]) && !empty($product_custom_fields['SKU'][0])) :
			$this->variation_has_sku = true;
			$this->sku = $product_custom_fields['SKU'][0];
		endif;

		if (isset($product_custom_fields['stock'][0]) && !empty($product_custom_fields['stock'][0])) :
			$this->variation_has_stock = true;
			$this->stock = $product_custom_fields['stock'][0];
		endif;

		if (isset($product_custom_fields['weight'][0]) && !empty($product_custom_fields['weight'][0])) :
			$this->variation_has_weight = true;
			$this->data['weight'] = $product_custom_fields['weight'][0];
		endif;

		if (isset($product_custom_fields['price'][0]) && !empty($product_custom_fields['price'][0])) :
			$this->variation_has_price = true;
			$this->price = $product_custom_fields['price'][0];
		endif;

		if (isset($product_custom_fields['sale_price'][0]) && !empty($product_custom_fields['sale_price'][0])) :
			$this->variation_has_sale_price = true;
			$this->data['sale_price'] = $product_custom_fields['sale_price'][0];
		endif;
	}

	/** Get the product's post data */
	function get_variation_post_data() {
		if (empty($this->variation)) :
			$this->variation = get_post( $this->variation_id );
		endif;
		return $this->variation;
	}

	/** Returns whether variation is on sale based on parent sale dates */
	function is_variation_on_sale() {

		$on_sale = false;

		if ( $this->variation_has_price ) :
			if ( $this->variation_has_sale_price ) :

				$parent_custom_fields = get_post_custom( $this->id );
				$date_from = $parent_custom_fields['sale_price_dates_from'][0];
				$date_to = $parent_custom_fields['sale_price_dates_to'][0];

				if ( $this->data['sale_price'] && $date_to == '' && $date_from == '' ) :
					$on_sale = true;
				endif;

				if ( $date_from && strtotime( $date_from ) < strtotime( 'NOW' )) :
					$on_sale = true;
				endif;

			endif;
		else :
			$on_sale = parent::is_on_sale();
		endif;

		return $on_sale;
	}

	/** Returns the product's price */
	function get_price() {

		if ($this->variation_has_price) :
			if ($this->variation_has_sale_price) :
				return $this->data['sale_price'];
			else :
				return $this->price;
			endif;
		else :
			return parent::get_price();
		endif;

	}

	/** Returns the price in html format */
	function get_price_html() {
		$price = '';
		if ($this->variation_has_price) :
			if ($this->variation_has_sale_price) :
				$price .= '<del>'.jigoshop_price( $this->price ).'</del> <ins>'.jigoshop_price( $this->data['sale_price'] ).'</ins>';
			else :
				$price .= jigoshop_price( $this->price );
			endif;
		else :
			$price = parent::get_price_html();
		endif;
		return $price;
	}

	/**
	 * Reduce stock level of the product
	 *
	 * @param   int		$by		Amount to reduce by
	 */
	function reduce_stock( $by = 1 ) {
		if ($this->variation_has_stock) :
			if ($this->managing_stock()) :
				$reduce_to = $this->stock - $by;
				update_post_meta($this->variation_id, 'stock', $reduce_to);
				return $reduce_to;
			endif;
		else :
			return parent::reduce_stock( $by );
		endif;
	}

	/**
	 * Increase stock level of the product
	 *
	 * @param   int		$by		Amount to increase by
	 */
	function increase_stock( $by = 1 ) {
		if ($this->variation_has_stock) :
			if ($this->managing_stock()) :
				$increase_to = $this->stock + $by;
				update_post_meta($this->variation_id, 'stock', $increase_to);
				return $increase_to;
			endif;
		else :
			return parent::increase_stock( $by );
		endif;
	}

}