<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2011
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @package MShop
 * @subpackage Plugin
 */


namespace Aimeos\MShop\Plugin\Provider\Order;


/**
 * Checks if ordered product sum and count of products is above a certain value.
 *
 * @package MShop
 * @subpackage Plugin
 */
class BasketLimits
	extends \Aimeos\MShop\Plugin\Provider\Factory\Base
	implements \Aimeos\MShop\Plugin\Provider\Factory\Iface
{
	/**
	 * Subscribes itself to a publisher
	 *
	 * @param \Aimeos\MW\Observer\Publisher\Iface $p Object implementing publisher interface
	 */
	public function register( \Aimeos\MW\Observer\Publisher\Iface $p )
	{
		$p->addListener( $this, 'check.after' );
	}


	/**
	 * Receives a notification from a publisher object
	 *
	 * @param \Aimeos\MW\Observer\Publisher\Iface $order Shop basket instance implementing publisher interface
	 * @param string $action Name of the action to listen for
	 * @param mixed $value Object or value changed in publisher
	 * @throws \Aimeos\MShop\Plugin\Provider\Exception if checks fail
	 * @return bool true if checks succeed
	 */
	public function update( \Aimeos\MW\Observer\Publisher\Iface $order, $action, $value = null )
	{
		$class = '\\Aimeos\\MShop\\Order\\Item\\Base\\Iface';
		if( !( $order instanceof $class ) ) {
			throw new \Aimeos\MShop\Plugin\Exception( sprintf( 'Object is not of required type "%1$s"', $class ) );
		}

		if( !( $value & \Aimeos\MShop\Order\Item\Base\Base::PARTS_PRODUCT ) ) {
			return true;
		}

		$context = $this->getContext();

		/** mshop/plugin/provider/order/complete/disable
		 * Disables the basket limits check
		 *
		 * If the BasketLimits plug-in is enabled, it enforces the configured
		 * limits before customers or anyone on behalf of them can continue the
		 * checkout process.
		 *
		 * This option enables e.g. call center agents to place orders which
		 * doesn't satisfy all requirements. It may be useful if you want to
		 * allow them to send free or replacements for lost or damaged products.
		 *
		 * @param boolean True to disable the check, false to keep it enabled
		 * @category Developer
		 * @category User
		 * @since 2014.03
		 */
		if( $context->getConfig()->get( 'mshop/plugin/provider/order/complete/disable', false ) ) {
			return true;
		}


		$count = 0;
		$sum = \Aimeos\MShop\Factory::createManager( $context, 'price' )->createItem();

		foreach( $order->getProducts() as $product )
		{
			$sum->addItem( $product->getPrice(), $product->getQuantity() );
			$count += $product->getQuantity();
		}

		$this->checkLimits( $sum, $count );

		return true;
	}


	/**
	 * Checks for the configured basket limits.
	 *
	 * @param \Aimeos\MShop\Price\Item\Iface $sum Total sum of all product price items
	 * @param integer $count Total number of products in the basket
	 * @throws \Aimeos\MShop\Plugin\Provider\Exception If one of the minimum or maximum limits is exceeded
	 */
	protected function checkLimits( \Aimeos\MShop\Price\Item\Iface $sum, $count )
	{
		$currencyId = $sum->getCurrencyId();
		$config = $this->getItemBase()->getConfig();

		if( ( isset( $config['min-value'][$currencyId] ) ) && ( $sum->getValue() + $sum->getRebate() < $config['min-value'][$currencyId] ) )
		{
			$msg = sprintf( 'The minimum basket value of %1$s isn\'t reached', $config['min-value'][$currencyId] );
			throw new \Aimeos\MShop\Plugin\Provider\Exception( $msg );
		}

		if( ( isset( $config['max-value'][$currencyId] ) ) && ( $sum->getValue() + $sum->getRebate() > $config['max-value'][$currencyId] ) )
		{
			$msg = sprintf( 'The maximum basket value of %1$s is exceeded', $config['max-value'][$currencyId] );
			throw new \Aimeos\MShop\Plugin\Provider\Exception( $msg );
		}

		if( ( isset( $config['min-products'] ) ) && ( $count < $config['min-products'] ) )
		{
			$msg = sprintf( 'The minimum product quantity of %1$d isn\'t reached', $config['min-products'] );
			throw new \Aimeos\MShop\Plugin\Provider\Exception( $msg );
		}

		if( ( isset( $config['max-products'] ) ) && ( $count > $config['max-products'] ) )
		{
			$msg = sprintf( 'The maximum product quantity of %1$d is exceeded', $config['max-products'] );
			throw new \Aimeos\MShop\Plugin\Provider\Exception( $msg );
		}
	}
}
