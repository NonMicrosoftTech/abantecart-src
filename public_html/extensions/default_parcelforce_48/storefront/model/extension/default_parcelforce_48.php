<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011-2014 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  Lincence details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if (!defined('DIR_CORE')) {
	header('Location: static_pages/');
}

class ModelExtensionDefaultParcelforce48 extends Model {
	function getQuote($address) {
		$this->load->language('default_parcelforce_48/default_parcelforce_48');

		if ($this->config->get('default_parcelforce_48_status')) {

			if (!$this->config->get('default_parcelforce_48_location_id')) {
				$status = TRUE;
			} else {
				$query = $this->db->query("SELECT *
                                            FROM " . $this->db->table('zones_to_locations') . "
                                            WHERE location_id = '" . (int)$this->config->get('default_parcelforce_48_location_id') . "'
                                                AND country_id = '" . (int)$address['country_id'] . "'
                                                AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
                if ($query->num_rows) {
                    $status = TRUE;
                } else {
                    $status = FALSE;
                }
			}
		} else {
			$status = FALSE;
		}

		$method_data = array();

		if (!$status) {
			return $method_data;
		}


		$basic_products = $this->cart->basicShippingProducts();;
		foreach ($basic_products as $product) {
			$product_ids[] = $product['product_id'];
		}
		$weight = $this->weight->convert($this->cart->getWeight($product_ids), $this->config->get('config_weight_class'), 'kgs');
		$sub_total = $this->cart->getSubTotal();
		$quote_data = $this->_processRate($weight, $sub_total);

		$special_ship_products = $this->cart->specialShippingProducts();
		foreach ($special_ship_products as $product) {
			$weight = $this->weight->convert($this->cart->getWeight(array($product['product_id'])), $this->config->get('config_weight_class'), 'kgs');

			//check if free or fixed shipping
			$fixed_cost = -1;
			$new_quote_data = array();
			if ($product['free_shipping']) {
				$fixed_cost = 0;
			} else if ($product['shipping_price'] > 0) {
				$fixed_cost = $product['shipping_price'];
				//If ship individually count every quintaty
				if ($product['ship_individually']) {
					$fixed_cost = $fixed_cost * $product['quantity'];
				}
				$fixed_cost = $this->currency->convert($fixed_cost, $this->config->get('config_currency'), $this->currency->getCode());
			} else {
				$new_quote_data = $this->_processRate($weight, $sub_total);
			}
		}

		//merge data and accumulate shipping cost
		if ($quote_data) {
			foreach ($quote_data as $key => $value) {

				if ($fixed_cost >= 0) {
					$quote_data[$key]['cost'] = (float)$quote_data[$key]['cost'] + $fixed_cost;
				} else {
					$quote_data[$key]['cost'] = (float)$quote_data[$key]['cost'] + $new_quote_data[$key]['cost'];
				}

				$quote_data[$key]['text'] = $this->currency->format(
						$this->tax->calculate(
								$this->currency->convert($quote_data[$key]['cost'], $this->config->get('config_currency'), $this->currency->getCode()),
								$this->config->get('default_parcelforce_48_tax'), $this->config->get('config_tax')
						)
				);
			}
		} else if ($new_quote_data) {
			$quote_data = $new_quote_data;
		}

		if ($quote_data) {
			$method_data = array(
					'id' => 'default_parcelforce_48',
					'title' => $this->language->get('text_title'),
					'quote' => $quote_data,
					'sort_order' => $this->config->get('default_parcelforce_48_sort_order'),
					'error' => FALSE
			);
		}


		return $method_data;
	}

	private function _processRate($weight, $sub_total) {

		$rates = explode(',', $this->config->get('default_parcelforce_48_rate'));

		foreach ($rates as $rate) {
			$data = explode(':', $rate);

			if ($data[0] >= $weight) {
				if (isset($data[1])) {
					$cost = $data[1];
				}

				break;
			}
		}

		$rates = explode(',', $this->config->get('default_parcelforce_48_compensation'));

		foreach ($rates as $rate) {
			$data = explode(':', $rate);

			if ($data[0] >= $sub_total) {
				if (isset($data[1])) {
					$compensation = $data[1];
				}

				break;
			}
		}


		$text = $this->language->get('text_description');

		if ($this->config->get('default_parcelforce_48_display_weight')) {
			$text .= ' (' . $this->language->get('text_weight') . ' ' . $this->weight->format($weight, $this->config->get('config_weight_class')) . ')';
		}

		if ($this->config->get('default_parcelforce_48_display_insurance') && (float)$compensation) {
			$text .= ' (' . $this->language->get('text_insurance') . ' ' . $this->currency->format($compensation) . ')';
		}

		if ($this->config->get('default_parcelforce_48_display_time')) {
			$text .= ' (' . $this->language->get('text_time') . ')';
		}

		$quote_data['default_parcelforce_48'] = array(
				'id' => 'default_parcelforce_48.default_parcelforce_48',
				'title' => $text,
				'cost' => $cost,
				'tax_class_id' => $this->config->get('default_parcelforce_48_tax'),
				'text' => $this->currency->format($this->tax->calculate($cost, $this->config->get('default_parcelforce_48_tax'), $this->config->get('config_tax')))
		);

		return $quote_data;
	}
}