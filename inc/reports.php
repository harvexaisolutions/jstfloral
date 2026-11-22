<?php
/**
 * Reports for the shop owner: month figures, 12-month charts and downloadable
 * Excel / PDF sheets of orders received and orders fulfilled.
 *
 * Orders & Deliveries → Reports.
 *
 * "Orders received" = orders placed in the period (what the shop should get).
 * "Orders fulfilled" = orders marked delivered in the period (what the shop got).
 *
 * @package Fleuriste
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lib/class-fleuriste-xlsx.php';
require_once __DIR__ . '/lib/class-fleuriste-pdf.php';

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'fleuriste-deliveries', __( 'Reports', 'fleuriste' ), __( 'Reports', 'fleuriste' ), 'view_woocommerce_reports', 'fleuriste-reports', 'fleuriste_reports_page', 4 );
	},
	20
);

/* -------------------------------------------------------------------------
 * Period and data
 * ---------------------------------------------------------------------- */

/**
 * Read the period from a request: month=YYYY-MM, or from/to=YYYY-MM-DD.
 *
 * @param array $src Request values.
 * @return array from, to, start, end (timestamps), label, month, slug.
 */
function fleuriste_report_period( $src ) {
	$tz    = wp_timezone();
	$now   = new DateTimeImmutable( 'now', $tz );
	$from  = isset( $src['from'] ) ? sanitize_text_field( wp_unslash( $src['from'] ) ) : '';
	$to    = isset( $src['to'] ) ? sanitize_text_field( wp_unslash( $src['to'] ) ) : '';
	$month = isset( $src['month'] ) ? sanitize_text_field( wp_unslash( $src['month'] ) ) : '';
	$valid = function ( $d ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && DateTimeImmutable::createFromFormat( '!Y-m-d', $d );
	};
	if ( $valid( $from ) && $valid( $to ) && ! $month ) {
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		$a     = new DateTimeImmutable( $from . ' 00:00:00', $tz );
		$b     = new DateTimeImmutable( $to . ' 23:59:59', $tz );
		$label = date_i18n( 'j M Y', $a->getTimestamp() + $a->getOffset() ) . ' – ' . date_i18n( 'j M Y', $b->getTimestamp() + $b->getOffset() );
		$month = '';
		$slug  = $from . '_' . $to;
	} else {
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = $now->format( 'Y-m' );
		}
		$a     = new DateTimeImmutable( $month . '-01 00:00:00', $tz );
		$b     = $a->modify( 'last day of this month' )->setTime( 23, 59, 59 );
		$label = date_i18n( 'F Y', $a->getTimestamp() + $a->getOffset() );
		$slug  = $month;
	}
	return array(
		'from'  => $a->format( 'Y-m-d' ),
		'to'    => $b->format( 'Y-m-d' ),
		'start' => $a->getTimestamp(),
		'end'   => $b->getTimestamp(),
		'label' => $label,
		'month' => $month,
		'slug'  => $slug,
	);
}

/**
 * Local date text for a timestamp.
 *
 * @param int    $ts     Timestamp.
 * @param string $format Format.
 * @return string
 */
function fleuriste_report_date( $ts, $format = 'd/m/Y' ) {
	return $ts ? wp_date( $format, $ts ) : '';
}

/**
 * One order as a report row.
 *
 * @param WC_Order $order Order.
 * @return array
 */
function fleuriste_report_row( $order ) {
	$items    = array();
	$delivery = '';
	foreach ( $order->get_items() as $item ) {
		$items[]  = $item->get_quantity() . ' × ' . $item->get_name();
		$delivery = $delivery ? $delivery : (string) $item->get_meta( '_fleuriste_delivery_date' );
	}
	$driver  = function_exists( 'fleuriste_driver' ) ? fleuriste_driver( (string) $order->get_meta( defined( 'FLEURISTE_DRIVER_META' ) ? FLEURISTE_DRIVER_META : '_fleuriste_driver' ) ) : null;
	$created = $order->get_date_created();
	$done    = $order->get_date_completed();
	$paid    = $order->get_date_paid();
	$total   = (float) $order->get_total();
	$refund  = (float) $order->get_total_refunded();
	$method  = trim( (string) $order->get_payment_method_title() );
	return array(
		'id'        => $order->get_id(),
		'number'    => '#' . $order->get_order_number(),
		'created'   => $created ? $created->getTimestamp() : 0,
		'completed' => $done ? $done->getTimestamp() : 0,
		'status'    => wc_get_order_status_name( $order->get_status() ),
		'customer'  => trim( $order->get_formatted_billing_full_name() ),
		'phone'     => $order->get_billing_phone(),
		'email'     => $order->get_billing_email(),
		'recipient' => trim( $order->get_formatted_shipping_full_name() ),
		'region'    => $order->get_shipping_city(),
		'delivery'  => $delivery,
		'items'     => implode( ', ', $items ),
		'payment'   => '' !== $method ? $method : __( 'Not set', 'fleuriste' ),
		'paid'      => (bool) $paid,
		'fee'       => (float) $order->get_shipping_total(),
		'total'     => $total,
		'refunded'  => $refund,
		'net'       => $total - $refund,
		'driver'    => $driver ? $driver['name'] : '',
		'source'    => 'fleuriste-phone' === $order->get_created_via() ? __( 'Phone / shop', 'fleuriste' ) : __( 'Website', 'fleuriste' ),
		'cancel'    => (string) $order->get_meta( '_fleuriste_cancel_reason' ),
		'lines'     => array_values(
			array_map(
				function ( $item ) {
					return array(
						'name'  => $item->get_name(),
						'qty'   => (int) $item->get_quantity(),
						'total' => (float) $item->get_total() + (float) $item->get_total_tax(),
					);
				},
				$order->get_items()
			)
		),
	);
}

/**
 * Orders placed in a period, split into counted orders and cancelled ones.
 *
 * Counted: every order except cancelled/refunded, failed and abandoned online
 * payments. Orders taken by phone that wait for a payment link are counted.
 *
 * @param int  $start Start timestamp.
 * @param int  $end   End timestamp.
 * @param bool $rows  Return report rows (true) or order objects (false).
 * @return array orders, cancelled.
 */
function fleuriste_report_received( $start, $end, $rows = true ) {
	$orders = wc_get_orders(
		array(
			'type'         => 'shop_order',
			'limit'        => -1,
			'orderby'      => 'date',
			'order'        => 'ASC',
			'date_created' => $start . '...' . $end,
			'status'       => array( 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-preparing', 'wc-out-for-delivery', 'wc-completed', 'wc-cancelled', 'wc-refunded' ),
		)
	);
	$out = array(
		'orders'    => array(),
		'cancelled' => array(),
	);
	foreach ( $orders as $order ) {
		if ( $order->has_status( 'pending' ) && 'fleuriste-phone' !== $order->get_created_via() ) {
			continue; // Abandoned online payment.
		}
		$key           = $order->has_status( array( 'cancelled', 'refunded' ) ) ? 'cancelled' : 'orders';
		$out[ $key ][] = $rows ? fleuriste_report_row( $order ) : $order;
	}
	return $out;
}

/**
 * Orders marked delivered (completed) in a period.
 *
 * @param int  $start Start timestamp.
 * @param int  $end   End timestamp.
 * @param bool $rows  Return report rows.
 * @return array
 */
function fleuriste_report_fulfilled( $start, $end, $rows = true ) {
	$orders = wc_get_orders(
		array(
			'type'           => 'shop_order',
			'limit'          => -1,
			'status'         => array( 'wc-completed' ),
			'date_completed' => $start . '...' . $end,
		)
	);
	$list = $rows ? array_map( 'fleuriste_report_row', $orders ) : $orders;
	if ( $rows ) {
		usort(
			$list,
			function ( $a, $b ) {
				return $a['completed'] <=> $b['completed'];
			}
		);
	}
	return $list;
}

/**
 * Sum a column.
 *
 * @param array  $rows Rows.
 * @param string $key  Column.
 * @return float
 */
function fleuriste_report_sum( $rows, $key ) {
	return (float) array_sum( wp_list_pluck( $rows, $key ) );
}

/**
 * Totals per payment method.
 *
 * @param array  $rows Rows.
 * @param string $key  Amount column.
 * @return array method => [count, amount]
 */
function fleuriste_report_by_payment( $rows, $key = 'total' ) {
	$out = array();
	foreach ( $rows as $r ) {
		if ( ! isset( $out[ $r['payment'] ] ) ) {
			$out[ $r['payment'] ] = array( 0, 0.0 );
		}
		++$out[ $r['payment'] ][0];
		$out[ $r['payment'] ][1] += $r[ $key ];
	}
	uasort(
		$out,
		function ( $a, $b ) {
			return $b[1] <=> $a[1];
		}
	);
	return $out;
}

/**
 * Money as plain text ("Rs 1,890").
 *
 * @param float $n Amount.
 * @return string
 */
function fleuriste_report_money( $n ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $n ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Twelve months ending with a given month, with received / fulfilled figures.
 *
 * @param string $end_month YYYY-MM.
 * @return array[]
 */
function fleuriste_report_months( $end_month ) {
	$tz     = wp_timezone();
	$last   = new DateTimeImmutable( $end_month . '-01 00:00:00', $tz );
	$first  = $last->modify( '-11 months' );
	$months = array();
	for ( $m = $first; $m <= $last; $m = $m->modify( '+1 month' ) ) {
		$months[ $m->format( 'Y-m' ) ] = array(
			'key'         => $m->format( 'Y-m' ),
			'short'       => date_i18n( 'M', $m->getTimestamp() + $m->getOffset() ),
			'long'        => date_i18n( 'F Y', $m->getTimestamp() + $m->getOffset() ),
			'year'        => $m->format( 'Y' ),
			'received'    => 0.0,
			'received_n'  => 0,
			'fulfilled'   => 0.0,
			'fulfilled_n' => 0,
			'cancelled_n' => 0,
		);
	}
	$start = $first->getTimestamp();
	$end   = $last->modify( 'last day of this month' )->setTime( 23, 59, 59 )->getTimestamp();
	$recv  = fleuriste_report_received( $start, $end, false );
	foreach ( $recv['orders'] as $o ) {
		$k = wp_date( 'Y-m', $o->get_date_created()->getTimestamp() );
		if ( isset( $months[ $k ] ) ) {
			$months[ $k ]['received'] += (float) $o->get_total();
			++$months[ $k ]['received_n'];
		}
	}
	foreach ( $recv['cancelled'] as $o ) {
		$k = wp_date( 'Y-m', $o->get_date_created()->getTimestamp() );
		if ( isset( $months[ $k ] ) ) {
			++$months[ $k ]['cancelled_n'];
		}
	}
	foreach ( fleuriste_report_fulfilled( $start, $end, false ) as $o ) {
		$k = wp_date( 'Y-m', $o->get_date_completed()->getTimestamp() );
		if ( isset( $months[ $k ] ) ) {
			$months[ $k ]['fulfilled'] += (float) $o->get_total() - (float) $o->get_total_refunded();
			++$months[ $k ]['fulfilled_n'];
		}
	}
	return array_values( $months );
}

/* -------------------------------------------------------------------------
 * Charts (inline SVG)
 * ---------------------------------------------------------------------- */

/**
 * A round number at or above $n for the top of an axis.
 *
 * @param float $n Largest value.
 * @return float
 */
function fleuriste_report_nice_max( $n ) {
	if ( $n <= 0 ) {
		return 4;
	}
	// Four equal steps of a round size (1, 2, 2.5, 5 × 10^k), so every tick is a clean number.
	$raw = $n / 4;
	$p   = pow( 10, floor( log10( $raw ) ) );
	foreach ( array( 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10 ) as $f ) {
		$step = $f * $p;
		if ( $step < 1 || ( $step < 10 && floor( $step ) != $step ) ) { // phpcs:ignore Universal.Operators.StrictComparisons
			continue;
		}
		if ( $step >= $raw ) {
			return $step * 4;
		}
	}
	return 40 * $p;
}

/**
 * Short number for axis ticks: 0, 500, 1.5K, 12K, 1.2M.
 *
 * @param float $n Number.
 * @return string
 */
function fleuriste_report_short( $n ) {
	if ( $n >= 1000000 ) {
		return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'M';
	}
	if ( $n >= 1000 ) {
		return rtrim( rtrim( number_format( $n / 1000, 1 ), '0' ), '.' ) . 'K';
	}
	return (string) round( $n );
}

/**
 * Grouped column chart (two series) or line chart, as SVG.
 *
 * @param array  $months  Month rows.
 * @param array  $series  [ key, label, colour ] × 2.
 * @param string $type    'columns' or 'lines'.
 * @param bool   $money   Values are money?
 * @param string $current Highlighted month key.
 * @return string
 */
function fleuriste_report_chart( $months, $series, $type, $money, $current ) {
	$w    = 760;
	$h    = 270;
	$l    = 58;
	$r    = 14;
	$t    = 14;
	$b    = 40;
	$pw   = $w - $l - $r;
	$ph   = $h - $t - $b;
	$n    = count( $months );
	$max  = 0;
	foreach ( $months as $m ) {
		foreach ( $series as $s ) {
			$max = max( $max, $m[ $s[0] ] );
		}
	}
	$max   = fleuriste_report_nice_max( $max );
	$slot  = $pw / $n;
	$y     = function ( $v ) use ( $t, $ph, $max ) {
		return $t + $ph - ( $v / $max ) * $ph;
	};
	$fmt   = function ( $v ) use ( $money ) {
		return $money ? fleuriste_report_money( $v ) : number_format_i18n( $v );
	};
	$svg   = '<svg class="fl-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" preserveAspectRatio="xMidYMid meet">';
	// Grid and y-axis.
	for ( $i = 0; $i <= 4; $i++ ) {
		$v    = $max * $i / 4;
		$yy   = round( $y( $v ) ) + 0.5;
		$svg .= '<line x1="' . $l . '" x2="' . ( $w - $r ) . '" y1="' . $yy . '" y2="' . $yy . '" class="fl-chart__grid' . ( 0 === $i ? ' is-base' : '' ) . '"/>';
		$svg .= '<text x="' . ( $l - 8 ) . '" y="' . ( $yy + 4 ) . '" text-anchor="end" class="fl-chart__tick">' . esc_html( fleuriste_report_short( $v ) ) . '</text>';
	}
	// Month labels.
	foreach ( $months as $i => $m ) {
		$cx    = $l + $slot * ( $i + 0.5 );
		$cls   = $m['key'] === $current ? ' is-current' : '';
		$svg  .= '<text x="' . round( $cx, 1 ) . '" y="' . ( $h - $b + 18 ) . '" text-anchor="middle" class="fl-chart__tick' . $cls . '">' . esc_html( $m['short'] ) . '</text>';
		if ( 0 === $i || '01' === substr( $m['key'], 5 ) ) {
			$svg .= '<text x="' . round( $cx, 1 ) . '" y="' . ( $h - $b + 32 ) . '" text-anchor="middle" class="fl-chart__tick fl-chart__year">' . esc_html( $m['year'] ) . '</text>';
		}
	}
	if ( 'columns' === $type ) {
		$bw  = min( 18, ( $slot - 14 ) / 2 );
		$gap = 2;
		foreach ( $months as $i => $m ) {
			$x0 = $l + $slot * ( $i + 0.5 ) - $bw - $gap / 2;
			foreach ( $series as $k => $s ) {
				$v  = $m[ $s[0] ];
				$x  = $x0 + $k * ( $bw + $gap );
				$top = $y( $v );
				$hh  = $t + $ph - $top;
				if ( $hh < 0.5 ) {
					continue;
				}
				$rad  = min( 4, $hh, $bw / 2 );
				$base = $t + $ph;
				$d    = sprintf( 'M%.1F %.1F V%.1F Q%.1F %.1F %.1F %.1F H%.1F Q%.1F %.1F %.1F %.1F V%.1F Z', $x, $base, $top + $rad, $x, $top, $x + $rad, $top, $x + $bw - $rad, $x + $bw, $top, $x + $bw, $top + $rad, $base );
				$svg .= '<path d="' . $d . '" fill="' . esc_attr( $s[2] ) . '" class="fl-chart__mark"/>';
			}
		}
	} else {
		foreach ( $series as $s ) {
			$pts = array();
			foreach ( $months as $i => $m ) {
				$pts[] = round( $l + $slot * ( $i + 0.5 ), 1 ) . ',' . round( $y( $m[ $s[0] ] ), 1 );
			}
			$svg .= '<polyline points="' . implode( ' ', $pts ) . '" fill="none" stroke="' . esc_attr( $s[2] ) . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
			foreach ( $months as $i => $m ) {
				$svg .= '<circle cx="' . round( $l + $slot * ( $i + 0.5 ), 1 ) . '" cy="' . round( $y( $m[ $s[0] ] ), 1 ) . '" r="4" fill="' . esc_attr( $s[2] ) . '" stroke="#fff" stroke-width="2"/>';
			}
		}
	}
	// Hover targets: one per month, covering the whole column.
	foreach ( $months as $i => $m ) {
		$tip = '<strong>' . esc_html( $m['long'] ) . '</strong>';
		foreach ( $series as $s ) {
			$tip .= '<span><i style="background:' . esc_attr( $s[2] ) . '"></i>' . esc_html( $s[1] ) . ' <b>' . esc_html( $fmt( $m[ $s[0] ] ) ) . '</b></span>';
		}
		$svg .= '<rect x="' . round( $l + $slot * $i, 1 ) . '" y="' . $t . '" width="' . round( $slot, 1 ) . '" height="' . $ph . '" class="fl-chart__hit" data-tip="' . esc_attr( $tip ) . '"/>';
	}
	return $svg . '</svg>';
}

/* -------------------------------------------------------------------------
 * The page
 * ---------------------------------------------------------------------- */

/**
 * Download link for a report.
 *
 * @param string $report received|fulfilled.
 * @param string $format xlsx|pdf.
 * @param array  $period Period.
 * @return string
 */
function fleuriste_report_url( $report, $format, $period ) {
	$args = array(
		'action' => 'fleuriste_report',
		'report' => $report,
		'format' => $format,
	);
	if ( $period['month'] ) {
		$args['month'] = $period['month'];
	} else {
		$args['from'] = $period['from'];
		$args['to']   = $period['to'];
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'fleuriste_report' );
}

/**
 * Orders & Deliveries → Reports.
 */
function fleuriste_reports_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
	$period    = fleuriste_report_period( $_GET );
	$recv      = fleuriste_report_received( $period['start'], $period['end'] );
	$done      = fleuriste_report_fulfilled( $period['start'], $period['end'] );
	$open      = array_filter(
		$recv['orders'],
		function ( $r ) {
			return wc_get_order_status_name( 'completed' ) !== $r['status'];
		}
	);
	$recv_sum  = fleuriste_report_sum( $recv['orders'], 'total' );
	$done_sum  = fleuriste_report_sum( $done, 'net' );
	$canc_sum  = fleuriste_report_sum( $recv['cancelled'], 'total' );
	$open_sum  = fleuriste_report_sum( $open, 'total' );
	$end_month = wp_date( 'Y-m', $period['end'] );
	$months    = fleuriste_report_months( $end_month );
	$c_recv    = '#9E4F63';
	$c_done    = '#2F7FB8';
	$tz        = wp_timezone();
	$this_m    = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m' );
	$last_m    = ( new DateTimeImmutable( 'first day of last month', $tz ) )->format( 'Y-m' );
	$prev_m    = ( new DateTimeImmutable( $end_month . '-01', $tz ) )->modify( '-1 month' )->format( 'Y-m' );
	$next_m    = ( new DateTimeImmutable( $end_month . '-01', $tz ) )->modify( '+1 month' )->format( 'Y-m' );
	$base      = admin_url( 'admin.php?page=fleuriste-reports' );

	// Payment methods (received vs fulfilled).
	$pay_r = fleuriste_report_by_payment( $recv['orders'] );
	$pay_d = fleuriste_report_by_payment( $done, 'net' );
	$pays  = array_unique( array_merge( array_keys( $pay_r ), array_keys( $pay_d ) ) );

	// Best sellers (orders received, not cancelled).
	$products = array();
	foreach ( $recv['orders'] as $r ) {
		foreach ( $r['lines'] as $line ) {
			if ( ! isset( $products[ $line['name'] ] ) ) {
				$products[ $line['name'] ] = array( 0, 0.0 );
			}
			$products[ $line['name'] ][0] += $line['qty'];
			$products[ $line['name'] ][1] += $line['total'];
		}
	}
	uasort(
		$products,
		function ( $a, $b ) {
			return $b[1] <=> $a[1];
		}
	);
	$products = array_slice( $products, 0, 10, true );

	// Deliveries per driver (fulfilled).
	$drivers = array();
	foreach ( $done as $r ) {
		$name = '' !== $r['driver'] ? $r['driver'] : __( 'No driver set', 'fleuriste' );
		if ( ! isset( $drivers[ $name ] ) ) {
			$drivers[ $name ] = array( 0, 0.0, 0.0 );
		}
		++$drivers[ $name ][0];
		$drivers[ $name ][1] += $r['net'];
		if ( false !== stripos( $r['payment'], 'cash' ) || false !== stripos( $r['payment'], 'delivery' ) ) {
			$drivers[ $name ][2] += $r['net'];
		}
	}
	ksort( $drivers );
	$money = 'fleuriste_report_money';
	?>
	<div class="wrap fl-rep">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Reports', 'fleuriste' ); ?></h1>

		<form class="fl-rep__filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="fleuriste-reports">
			<div class="fl-rep__nav">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $prev_m, $base ) ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 'fleuriste' ); ?>">‹</a>
				<label class="screen-reader-text" for="fl-rep-month"><?php esc_html_e( 'Month', 'fleuriste' ); ?></label>
				<input type="month" id="fl-rep-month" name="month" value="<?php echo esc_attr( $period['month'] ? $period['month'] : $end_month ); ?>" max="<?php echo esc_attr( $this_m ); ?>" onchange="this.form.submit()">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $next_m, $base ) ); ?>" aria-label="<?php esc_attr_e( 'Next month', 'fleuriste' ); ?>"<?php echo $next_m > $this_m ? ' hidden' : ''; ?>>›</a>
			</div>
			<a class="fl-rep__chip<?php echo $period['month'] === $this_m ? ' is-on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'month', $this_m, $base ) ); ?>"><?php esc_html_e( 'This month', 'fleuriste' ); ?></a>
			<a class="fl-rep__chip<?php echo $period['month'] === $last_m ? ' is-on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'month', $last_m, $base ) ); ?>"><?php esc_html_e( 'Last month', 'fleuriste' ); ?></a>
			<details class="fl-rep__range"<?php echo $period['month'] ? '' : ' open'; ?>>
				<summary><?php esc_html_e( 'Other dates', 'fleuriste' ); ?></summary>
				<span>
					<input type="date" name="from" value="<?php echo esc_attr( $period['from'] ); ?>" aria-label="<?php esc_attr_e( 'From', 'fleuriste' ); ?>" disabled>
					–
					<input type="date" name="to" value="<?php echo esc_attr( $period['to'] ); ?>" aria-label="<?php esc_attr_e( 'To', 'fleuriste' ); ?>" disabled>
					<button class="button" type="submit" name="range" value="1"><?php esc_html_e( 'Show', 'fleuriste' ); ?></button>
				</span>
			</details>
		</form>

		<h2 class="fl-rep__period"><?php echo esc_html( $period['label'] ); ?></h2>

		<div class="fl-rep__tiles">
			<div class="fl-rep__tile">
				<span class="fl-rep__label"><i style="background:<?php echo esc_attr( $c_recv ); ?>"></i><?php esc_html_e( 'Orders received', 'fleuriste' ); ?></span>
				<strong><?php echo esc_html( $money( $recv_sum ) ); ?></strong>
				<small><?php echo esc_html( sprintf( /* translators: %d: number of orders */ _n( '%d order placed', '%d orders placed', count( $recv['orders'] ), 'fleuriste' ), count( $recv['orders'] ) ) ); ?> · <?php esc_html_e( 'what you should get', 'fleuriste' ); ?></small>
			</div>
			<div class="fl-rep__tile">
				<span class="fl-rep__label"><i style="background:<?php echo esc_attr( $c_done ); ?>"></i><?php esc_html_e( 'Orders fulfilled', 'fleuriste' ); ?></span>
				<strong><?php echo esc_html( $money( $done_sum ) ); ?></strong>
				<small><?php echo esc_html( sprintf( /* translators: %d: number of orders */ _n( '%d order delivered', '%d orders delivered', count( $done ), 'fleuriste' ), count( $done ) ) ); ?> · <?php esc_html_e( 'what you got', 'fleuriste' ); ?></small>
			</div>
			<div class="fl-rep__tile">
				<span class="fl-rep__label"><?php esc_html_e( 'Still to deliver', 'fleuriste' ); ?></span>
				<strong><?php echo esc_html( $money( $open_sum ) ); ?></strong>
				<small><?php echo esc_html( sprintf( /* translators: %d: number of orders */ _n( '%d order from this period', '%d orders from this period', count( $open ), 'fleuriste' ), count( $open ) ) ); ?></small>
			</div>
			<div class="fl-rep__tile">
				<span class="fl-rep__label"><?php esc_html_e( 'Cancelled', 'fleuriste' ); ?></span>
				<strong><?php echo esc_html( $money( $canc_sum ) ); ?></strong>
				<small><?php echo esc_html( sprintf( /* translators: %d: number of orders */ _n( '%d order (not counted)', '%d orders (not counted)', count( $recv['cancelled'] ), 'fleuriste' ), count( $recv['cancelled'] ) ) ); ?></small>
			</div>
			<div class="fl-rep__tile">
				<span class="fl-rep__label"><?php esc_html_e( 'Average order', 'fleuriste' ); ?></span>
				<strong><?php echo esc_html( $money( count( $recv['orders'] ) ? $recv_sum / count( $recv['orders'] ) : 0 ) ); ?></strong>
				<small><?php esc_html_e( 'orders received', 'fleuriste' ); ?></small>
			</div>
		</div>

		<div class="fl-rep__downloads">
			<div class="fl-rep__dl">
				<div>
					<h3><?php esc_html_e( 'Orders received', 'fleuriste' ); ?></h3>
					<p><?php esc_html_e( 'Every order placed in the period with its amount and the total – what you should get. Cancelled orders are listed separately and not counted.', 'fleuriste' ); ?></p>
				</div>
				<div class="fl-rep__btns">
					<a class="button button-primary" href="<?php echo esc_url( fleuriste_report_url( 'received', 'xlsx', $period ) ); ?>"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span> <?php esc_html_e( 'Excel', 'fleuriste' ); ?></a>
					<a class="button" href="<?php echo esc_url( fleuriste_report_url( 'received', 'pdf', $period ) ); ?>"><span class="dashicons dashicons-pdf" aria-hidden="true"></span> <?php esc_html_e( 'PDF', 'fleuriste' ); ?></a>
				</div>
			</div>
			<div class="fl-rep__dl">
				<div>
					<h3><?php esc_html_e( 'Orders fulfilled', 'fleuriste' ); ?></h3>
					<p><?php esc_html_e( 'Every order marked delivered in the period, with refunds taken off – what you actually got, by payment method and driver.', 'fleuriste' ); ?></p>
				</div>
				<div class="fl-rep__btns">
					<a class="button button-primary" href="<?php echo esc_url( fleuriste_report_url( 'fulfilled', 'xlsx', $period ) ); ?>"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span> <?php esc_html_e( 'Excel', 'fleuriste' ); ?></a>
					<a class="button" href="<?php echo esc_url( fleuriste_report_url( 'fulfilled', 'pdf', $period ) ); ?>"><span class="dashicons dashicons-pdf" aria-hidden="true"></span> <?php esc_html_e( 'PDF', 'fleuriste' ); ?></a>
				</div>
			</div>
		</div>

		<?php
		$series_money = array( array( 'received', __( 'Received', 'fleuriste' ), $c_recv ), array( 'fulfilled', __( 'Fulfilled', 'fleuriste' ), $c_done ) );
		$series_count = array( array( 'received_n', __( 'Received', 'fleuriste' ), $c_recv ), array( 'fulfilled_n', __( 'Fulfilled', 'fleuriste' ), $c_done ) );
		$legend       = '<div class="fl-rep__legend"><span><i style="background:' . esc_attr( $c_recv ) . '"></i>' . esc_html__( 'Received (orders placed)', 'fleuriste' ) . '</span><span><i style="background:' . esc_attr( $c_done ) . '"></i>' . esc_html__( 'Fulfilled (orders delivered)', 'fleuriste' ) . '</span></div>';
		?>
		<div class="fl-rep__charts">
			<section class="fl-rep__card">
				<h3><?php esc_html_e( 'Amount per month', 'fleuriste' ); ?></h3>
				<p class="fl-rep__sub"><?php echo esc_html( sprintf( /* translators: 1: first month, 2: last month */ __( '%1$s to %2$s', 'fleuriste' ), $months[0]['long'], $months[11]['long'] ) ); ?></p>
				<?php echo $legend; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo fleuriste_report_chart( $months, $series_money, 'columns', true, $end_month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
			<section class="fl-rep__card">
				<h3><?php esc_html_e( 'Number of orders per month', 'fleuriste' ); ?></h3>
				<p class="fl-rep__sub"><?php echo esc_html( sprintf( /* translators: 1: first month, 2: last month */ __( '%1$s to %2$s', 'fleuriste' ), $months[0]['long'], $months[11]['long'] ) ); ?></p>
				<?php echo $legend; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo fleuriste_report_chart( $months, $series_count, 'lines', false, $end_month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
		</div>
		<div class="fl-rep__tip" id="fl-rep-tip" hidden></div>

		<div class="fl-rep__grid">
			<section class="fl-rep__card">
				<h3><?php esc_html_e( 'By payment method', 'fleuriste' ); ?></h3>
				<table class="fl-rep__table">
					<thead><tr><th><?php esc_html_e( 'Method', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Received', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Fulfilled', 'fleuriste' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $pays as $p ) : ?>
							<tr>
								<td><?php echo esc_html( $p ); ?></td>
								<td class="num"><?php echo esc_html( isset( $pay_r[ $p ] ) ? $money( $pay_r[ $p ][1] ) . ' (' . $pay_r[ $p ][0] . ')' : '–' ); ?></td>
								<td class="num"><?php echo esc_html( isset( $pay_d[ $p ] ) ? $money( $pay_d[ $p ][1] ) . ' (' . $pay_d[ $p ][0] . ')' : '–' ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! $pays ) : ?>
							<tr><td colspan="3" class="fl-rep__none"><?php esc_html_e( 'No orders in this period.', 'fleuriste' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>
			<section class="fl-rep__card">
				<h3><?php esc_html_e( 'Deliveries by driver', 'fleuriste' ); ?></h3>
				<table class="fl-rep__table">
					<thead><tr><th><?php esc_html_e( 'Driver', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Delivered', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Amount', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Cash collected', 'fleuriste' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $drivers as $name => $d ) : ?>
							<tr><td><?php echo esc_html( $name ); ?></td><td class="num"><?php echo (int) $d[0]; ?></td><td class="num"><?php echo esc_html( $money( $d[1] ) ); ?></td><td class="num"><?php echo esc_html( $d[2] ? $money( $d[2] ) : '–' ); ?></td></tr>
						<?php endforeach; ?>
						<?php if ( ! $drivers ) : ?>
							<tr><td colspan="4" class="fl-rep__none"><?php esc_html_e( 'No deliveries in this period.', 'fleuriste' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>
			<section class="fl-rep__card">
				<h3><?php esc_html_e( 'Best sellers', 'fleuriste' ); ?></h3>
				<table class="fl-rep__table">
					<thead><tr><th><?php esc_html_e( 'Product', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Sold', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Amount', 'fleuriste' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $products as $name => $p ) : ?>
							<tr><td><?php echo esc_html( $name ); ?></td><td class="num"><?php echo (int) $p[0]; ?></td><td class="num"><?php echo esc_html( $money( $p[1] ) ); ?></td></tr>
						<?php endforeach; ?>
						<?php if ( ! $products ) : ?>
							<tr><td colspan="3" class="fl-rep__none"><?php esc_html_e( 'No orders in this period.', 'fleuriste' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>
			<section class="fl-rep__card">
				<h3><?php esc_html_e( 'Month by month', 'fleuriste' ); ?></h3>
				<table class="fl-rep__table">
					<thead><tr><th><?php esc_html_e( 'Month', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Received', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Fulfilled', 'fleuriste' ); ?></th><th class="num"><?php esc_html_e( 'Cancelled', 'fleuriste' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( array_reverse( $months ) as $m ) : ?>
							<tr<?php echo $m['key'] === $end_month ? ' class="is-current"' : ''; ?>>
								<td><a href="<?php echo esc_url( add_query_arg( 'month', $m['key'], $base ) ); ?>"><?php echo esc_html( $m['long'] ); ?></a></td>
								<td class="num"><?php echo esc_html( $money( $m['received'] ) . ' (' . $m['received_n'] . ')' ); ?></td>
								<td class="num"><?php echo esc_html( $money( $m['fulfilled'] ) . ' (' . $m['fulfilled_n'] . ')' ); ?></td>
								<td class="num"><?php echo (int) $m['cancelled_n']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		</div>
		<p class="description"><?php esc_html_e( 'Received counts orders by the day they were placed. Fulfilled counts orders by the day they were marked delivered, so an order placed on the 31st and delivered on the 1st is received in one month and fulfilled in the next. Unpaid online checkouts that were abandoned are not counted; phone orders waiting for a payment link are.', 'fleuriste' ); ?></p>
	</div>
	<style>
		.fl-rep{max-width:1180px;--rose:#9E4F63;--ink:#2B1D21;--muted:#6f6266;--line:#ece4e5}
		.fl-rep__filters{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:14px 0 6px}
		.fl-rep__nav{display:flex;gap:4px;align-items:center}
		.fl-rep__nav input{min-height:32px}
		.fl-rep__chip{display:inline-block;padding:5px 12px;border:1px solid var(--line);border-radius:99px;background:#fff;text-decoration:none;color:var(--ink)}
		.fl-rep__chip.is-on{border-color:var(--rose);color:var(--rose);font-weight:600}
		.fl-rep__range summary{cursor:pointer;color:#2271b1;padding:5px 4px}
		.fl-rep__range[open]{display:flex;align-items:center;gap:8px}
		.fl-rep__range span{display:inline-flex;gap:6px;align-items:center}
		.fl-rep__period{font-size:22px;margin:18px 0 12px;color:var(--ink)}
		.fl-rep__tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}
		.fl-rep__tile,.fl-rep__card,.fl-rep__dl{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 18px}
		.fl-rep__tile strong{display:block;font-size:24px;line-height:1.2;margin:6px 0 4px;color:var(--ink);font-variant-numeric:tabular-nums}
		.fl-rep__tile small{color:var(--muted)}
		.fl-rep__label{font-weight:600;color:var(--muted);display:flex;align-items:center;gap:6px}
		.fl-rep__label i,.fl-rep__legend i,.fl-rep__tip i{display:inline-block;width:10px;height:10px;border-radius:3px}
		.fl-rep__downloads{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:12px;margin:14px 0}
		.fl-rep__dl{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap}
		.fl-rep__dl h3{margin:0 0 4px}
		.fl-rep__dl p{margin:0;color:var(--muted);max-width:430px}
		.fl-rep__btns{display:flex;gap:6px}
		.fl-rep__btns .button{display:inline-flex;align-items:center;gap:4px}
		.fl-rep__charts{display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:12px}
		.fl-rep__card h3{margin:0}
		.fl-rep__sub{margin:2px 0 8px;color:var(--muted)}
		.fl-rep__legend{display:flex;gap:16px;flex-wrap:wrap;margin:0 0 6px;color:var(--ink)}
		.fl-rep__legend span{display:inline-flex;align-items:center;gap:6px}
		.fl-chart{width:100%;height:auto;display:block}
		.fl-chart__grid{stroke:#eee7e8;stroke-width:1}
		.fl-chart__grid.is-base{stroke:#d9cfd1}
		.fl-chart__tick{font-size:11px;fill:#7a6d71}
		.fl-chart__tick.is-current{fill:var(--ink);font-weight:700}
		.fl-chart__year{font-size:10px}
		.fl-chart__hit{fill:transparent;cursor:default}
		.fl-chart__hit:hover{fill:rgba(158,79,99,.06)}
		.fl-rep__tip{position:fixed;z-index:99999;pointer-events:none;background:#fff;border:1px solid var(--line);box-shadow:0 6px 20px rgba(43,29,33,.14);border-radius:8px;padding:8px 10px;font-size:12px;line-height:1.6;color:var(--ink)}
		.fl-rep__tip span{display:flex;align-items:center;gap:6px}
		.fl-rep__tip b{margin-left:auto;padding-left:12px;font-variant-numeric:tabular-nums}
		.fl-rep__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:12px;margin:12px 0}
		.fl-rep__table{width:100%;border-collapse:collapse;margin-top:8px}
		.fl-rep__table th{text-align:left;color:var(--muted);font-weight:600;border-bottom:1px solid var(--line);padding:6px 4px}
		.fl-rep__table td{padding:6px 4px;border-bottom:1px solid #f4eeef}
		.fl-rep__table .num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
		.fl-rep__table tr.is-current td{font-weight:600;background:#fbf6f7}
		.fl-rep__none{color:var(--muted);font-style:italic}
		@media (max-width:600px){.fl-rep__charts,.fl-rep__grid,.fl-rep__downloads{grid-template-columns:1fr}.fl-rep__dl p{max-width:none}.fl-rep__charts .fl-rep__card{overflow-x:auto}.fl-chart{min-width:600px}}
	</style>
	<script>
	( function () {
		var tip = document.getElementById( 'fl-rep-tip' );
		document.querySelectorAll( '.fl-chart__hit' ).forEach( function ( el ) {
			el.addEventListener( 'mousemove', function ( e ) {
				tip.innerHTML = el.getAttribute( 'data-tip' );
				tip.hidden = false;
				var x = e.clientX + 14, w = tip.offsetWidth;
				if ( x + w > window.innerWidth - 8 ) { x = e.clientX - w - 14; }
				tip.style.left = x + 'px';
				tip.style.top = ( e.clientY + 12 ) + 'px';
			} );
			el.addEventListener( 'mouseleave', function () { tip.hidden = true; } );
		} );
		// "Other dates": dates are only sent when that panel is open.
		var range = document.querySelector( '.fl-rep__range' );
		var form = range.closest( 'form' );
		function sync() {
			range.querySelectorAll( 'input' ).forEach( function ( i ) { i.disabled = ! range.open; } );
			form.querySelector( '[name=month]' ).disabled = range.open;
		}
		range.addEventListener( 'toggle', sync );
		sync();
		form.querySelector( '[name=month]' ).addEventListener( 'change', function () { range.open = false; sync(); form.submit(); } );
	} )();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Downloads
 * ---------------------------------------------------------------------- */

add_action(
	'admin_post_fleuriste_report',
	function () {
		check_admin_referer( 'fleuriste_report' );
		if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fleuriste' ) );
		}
		$period = fleuriste_report_period( $_GET );
		$report = isset( $_GET['report'] ) && 'fulfilled' === $_GET['report'] ? 'fulfilled' : 'received';
		$format = isset( $_GET['format'] ) && 'pdf' === $_GET['format'] ? 'pdf' : 'xlsx';
		$shop   = sanitize_title( get_bloginfo( 'name' ) );
		$file   = ( $shop ? $shop : 'shop' ) . '-orders-' . $report . '-' . $period['slug'] . '.' . $format;

		if ( 'xlsx' === $format && ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel files need the PHP "zip" extension, which is not available on this server. Please use the PDF instead.', 'fleuriste' ) );
		}
		$bytes = 'received' === $report
			? ( 'pdf' === $format ? fleuriste_report_received_pdf( $period ) : fleuriste_report_received_xlsx( $period ) )
			: ( 'pdf' === $format ? fleuriste_report_fulfilled_pdf( $period ) : fleuriste_report_fulfilled_xlsx( $period ) );

		nocache_headers();
		header( 'Content-Type: ' . ( 'pdf' === $format ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File download.
		exit;
	}
);

/**
 * Currency prefix for Excel ("Rs").
 *
 * @return string
 */
function fleuriste_report_currency() {
	return trim( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ), " \xC2\xA0" );
}

/**
 * Header rows for an Excel sheet.
 *
 * @param string $title  Title.
 * @param array  $period Period.
 * @return array
 */
function fleuriste_report_xlsx_head( $title, $period ) {
	return array(
		array( array( 'v' => get_bloginfo( 'name' ) . ' – ' . $title . ' – ' . $period['label'], 's' => Fleuriste_XLSX::TITLE ) ),
		array( array( 'v' => sprintf( /* translators: 1: from, 2: to, 3: date */ __( 'From %1$s to %2$s · created %3$s', 'fleuriste' ), wp_date( 'd/m/Y', $period['start'] ), wp_date( 'd/m/Y', $period['end'] ), wp_date( 'd/m/Y H:i' ) ), 's' => Fleuriste_XLSX::MUTED ) ),
		array(),
	);
}

/**
 * Turn rows into Excel rows for the given columns, with a totals row and a
 * "by payment method" block using formulas.
 *
 * @param array  $cols     [label, key, type(text|money|date|datetime|int), width].
 * @param array  $rows     Report rows.
 * @param array  $head     Rows above the table.
 * @param string $pay_col  Key of the payment column (for SUMIF), or ''.
 * @param string $sum_key  Main amount key for the payment block.
 * @return array rows, widths.
 */
function fleuriste_report_xlsx_table( $cols, $rows, $head, $pay_col = '', $sum_key = 'total' ) {
	$out    = $head;
	$hdr    = array();
	$widths = array();
	foreach ( $cols as $c ) {
		$hdr[]    = array(
			'v' => $c[0],
			's' => in_array( $c[2], array( 'money', 'int' ), true ) ? Fleuriste_XLSX::HEADER_R : Fleuriste_XLSX::HEADER,
		);
		$widths[] = $c[3];
	}
	$out[]  = $hdr;
	$first  = count( $out ) + 1; // 1-based row of the first data row.
	foreach ( $rows as $r ) {
		$line = array();
		foreach ( $cols as $c ) {
			$v = $r[ $c[1] ];
			switch ( $c[2] ) {
				case 'money':
					$line[] = array(
						'v' => (float) $v,
						's' => Fleuriste_XLSX::MONEY,
					);
					break;
				case 'date':
					$line[] = $v ? array(
						'v' => is_numeric( $v ) ? (int) $v : strtotime( $v . ' 12:00:00' ),
						't' => 'd',
					) : '';
					break;
				case 'datetime':
					$line[] = $v ? array(
						'v' => (int) $v,
						't' => 'd',
						's' => Fleuriste_XLSX::DATETIME,
					) : '';
					break;
				case 'int':
					$line[] = (int) $v;
					break;
				default:
					$line[] = (string) $v;
			}
		}
		$out[] = $line;
	}
	$last = count( $out );
	// Totals.
	$tot = array();
	foreach ( $cols as $i => $c ) {
		$col = Fleuriste_XLSX::col( $i );
		if ( 0 === $i ) {
			$tot[] = array(
				'v' => __( 'Total', 'fleuriste' ),
				's' => Fleuriste_XLSX::BOLD,
			);
		} elseif ( 1 === $i ) {
			$tot[] = array(
				'v' => count( $rows ),
				'f' => $rows ? "COUNTA(A{$first}:A{$last})" : '0',
				's' => Fleuriste_XLSX::INT_BOLD,
			);
		} elseif ( 'money' === $c[2] ) {
			$tot[] = array(
				'v' => fleuriste_report_sum( $rows, $c[1] ),
				'f' => $rows ? "SUM({$col}{$first}:{$col}{$last})" : '0',
				's' => Fleuriste_XLSX::MONEY_BOLD,
			);
		} else {
			$tot[] = '';
		}
	}
	$out[] = array();
	$out[] = $tot;
	$out[] = array(
		'',
		array(
			'v' => _n( 'order', 'orders', count( $rows ), 'fleuriste' ),
			's' => Fleuriste_XLSX::MUTED,
		),
	);
	// By payment method.
	if ( $pay_col && $rows ) {
		$pi = array_search( $pay_col, wp_list_pluck( $cols, 1 ), true );
		$si = array_search( $sum_key, wp_list_pluck( $cols, 1 ), true );
		if ( false !== $pi && false !== $si ) {
			$pc    = Fleuriste_XLSX::col( $pi );
			$sc    = Fleuriste_XLSX::col( $si );
			$out[] = array();
			$out[] = array(
				array(
					'v' => __( 'By payment method', 'fleuriste' ),
					's' => Fleuriste_XLSX::BOLD,
				),
				array(
					'v' => __( 'Orders', 'fleuriste' ),
					's' => Fleuriste_XLSX::BOLD,
				),
				array(
					'v' => __( 'Amount', 'fleuriste' ),
					's' => Fleuriste_XLSX::BOLD,
				),
			);
			foreach ( fleuriste_report_by_payment( $rows, $sum_key ) as $method => $d ) {
				$r     = count( $out ) + 1;
				$out[] = array(
					$method,
					array(
						'v' => $d[0],
						'f' => "COUNTIF({$pc}{$first}:{$pc}{$last},A{$r})",
					),
					array(
						'v' => $d[1],
						'f' => "SUMIF({$pc}{$first}:{$pc}{$last},A{$r},{$sc}{$first}:{$sc}{$last})",
						's' => Fleuriste_XLSX::MONEY,
					),
				);
			}
		}
	}
	return array( $out, $widths );
}

/**
 * Line items sheet (one row per product sold).
 *
 * @param array $rows   Report rows.
 * @param array $period Period.
 * @param array $head   Header rows.
 * @return array rows, widths.
 */
function fleuriste_report_xlsx_items( $rows, $period, $head ) {
	$items = array();
	foreach ( $rows as $r ) {
		foreach ( $r['lines'] as $line ) {
			$items[] = array(
				'number'  => $r['number'],
				'created' => $r['created'],
				'name'    => $line['name'],
				'qty'     => $line['qty'],
				'total'   => $line['total'],
			);
		}
	}
	return fleuriste_report_xlsx_table(
		array(
			array( __( 'Order', 'fleuriste' ), 'number', 'text', 10 ),
			array( __( 'Order date', 'fleuriste' ), 'created', 'date', 12 ),
			array( __( 'Product', 'fleuriste' ), 'name', 'text', 40 ),
			array( __( 'Quantity', 'fleuriste' ), 'qty', 'int', 10 ),
			array( __( 'Amount', 'fleuriste' ), 'total', 'money', 14 ),
		),
		$items,
		$head
	);
}

/**
 * Orders received → Excel.
 *
 * @param array $period Period.
 * @return string
 */
function fleuriste_report_received_xlsx( $period ) {
	$data = fleuriste_report_received( $period['start'], $period['end'] );
	$cols = array(
		array( __( 'Order', 'fleuriste' ), 'number', 'text', 10 ),
		array( __( 'Order date', 'fleuriste' ), 'created', 'datetime', 16 ),
		array( __( 'Status', 'fleuriste' ), 'status', 'text', 14 ),
		array( __( 'Taken via', 'fleuriste' ), 'source', 'text', 12 ),
		array( __( 'Customer', 'fleuriste' ), 'customer', 'text', 22 ),
		array( __( 'Phone', 'fleuriste' ), 'phone', 'text', 14 ),
		array( __( 'Email', 'fleuriste' ), 'email', 'text', 24 ),
		array( __( 'Recipient', 'fleuriste' ), 'recipient', 'text', 20 ),
		array( __( 'Region', 'fleuriste' ), 'region', 'text', 14 ),
		array( __( 'Delivery date', 'fleuriste' ), 'delivery', 'date', 13 ),
		array( __( 'Items', 'fleuriste' ), 'items', 'text', 40 ),
		array( __( 'Payment', 'fleuriste' ), 'payment', 'text', 20 ),
		array( __( 'Delivery fee', 'fleuriste' ), 'fee', 'money', 13 ),
		array( __( 'Amount', 'fleuriste' ), 'total', 'money', 14 ),
	);
	foreach ( $data['orders'] as &$r ) {
		$r['payment_state'] = $r['paid'] ? __( 'Paid', 'fleuriste' ) : __( 'Not paid yet', 'fleuriste' );
	}
	unset( $r );
	array_splice( $cols, 12, 0, array( array( __( 'Paid?', 'fleuriste' ), 'payment_state', 'text', 12 ) ) );

	$x                = new Fleuriste_XLSX( fleuriste_report_currency(), wc_get_price_decimals() );
	$head             = fleuriste_report_xlsx_head( __( 'Orders received', 'fleuriste' ), $period );
	list( $rows, $w ) = fleuriste_report_xlsx_table( $cols, $data['orders'], $head, 'payment', 'total' );
	$x->add_sheet( __( 'Orders received', 'fleuriste' ), $rows, $w, 4 );

	$ccols = array(
		array( __( 'Order', 'fleuriste' ), 'number', 'text', 10 ),
		array( __( 'Order date', 'fleuriste' ), 'created', 'datetime', 16 ),
		array( __( 'Status', 'fleuriste' ), 'status', 'text', 14 ),
		array( __( 'Customer', 'fleuriste' ), 'customer', 'text', 22 ),
		array( __( 'Phone', 'fleuriste' ), 'phone', 'text', 14 ),
		array( __( 'Items', 'fleuriste' ), 'items', 'text', 40 ),
		array( __( 'Reason', 'fleuriste' ), 'cancel', 'text', 24 ),
		array( __( 'Payment', 'fleuriste' ), 'payment', 'text', 20 ),
		array( __( 'Amount', 'fleuriste' ), 'total', 'money', 14 ),
		array( __( 'Refunded', 'fleuriste' ), 'refunded', 'money', 14 ),
	);
	$reasons = function_exists( 'fleuriste_cancel_reasons' ) ? fleuriste_cancel_reasons() : array();
	foreach ( $data['cancelled'] as &$r ) {
		$r['cancel'] = isset( $reasons[ $r['cancel'] ] ) ? $reasons[ $r['cancel'] ] : $r['cancel'];
	}
	unset( $r );
	list( $rows, $w ) = fleuriste_report_xlsx_table( $ccols, $data['cancelled'], fleuriste_report_xlsx_head( __( 'Cancelled orders (not counted)', 'fleuriste' ), $period ) );
	$x->add_sheet( __( 'Cancelled', 'fleuriste' ), $rows, $w, 4 );

	list( $rows, $w ) = fleuriste_report_xlsx_items( $data['orders'], $period, fleuriste_report_xlsx_head( __( 'Products in orders received', 'fleuriste' ), $period ) );
	$x->add_sheet( __( 'Products', 'fleuriste' ), $rows, $w, 4 );
	return $x->build();
}

/**
 * Orders fulfilled → Excel.
 *
 * @param array $period Period.
 * @return string
 */
function fleuriste_report_fulfilled_xlsx( $period ) {
	$rows_data = fleuriste_report_fulfilled( $period['start'], $period['end'] );
	$cols      = array(
		array( __( 'Order', 'fleuriste' ), 'number', 'text', 10 ),
		array( __( 'Order date', 'fleuriste' ), 'created', 'date', 12 ),
		array( __( 'Delivered on', 'fleuriste' ), 'completed', 'datetime', 16 ),
		array( __( 'Customer', 'fleuriste' ), 'customer', 'text', 22 ),
		array( __( 'Phone', 'fleuriste' ), 'phone', 'text', 14 ),
		array( __( 'Recipient', 'fleuriste' ), 'recipient', 'text', 20 ),
		array( __( 'Region', 'fleuriste' ), 'region', 'text', 14 ),
		array( __( 'Items', 'fleuriste' ), 'items', 'text', 40 ),
		array( __( 'Payment', 'fleuriste' ), 'payment', 'text', 20 ),
		array( __( 'Driver', 'fleuriste' ), 'driver', 'text', 16 ),
		array( __( 'Delivery fee', 'fleuriste' ), 'fee', 'money', 13 ),
		array( __( 'Amount', 'fleuriste' ), 'total', 'money', 14 ),
		array( __( 'Refunded', 'fleuriste' ), 'refunded', 'money', 13 ),
		array( __( 'Received', 'fleuriste' ), 'net', 'money', 14 ),
	);
	$x                = new Fleuriste_XLSX( fleuriste_report_currency(), wc_get_price_decimals() );
	list( $rows, $w ) = fleuriste_report_xlsx_table( $cols, $rows_data, fleuriste_report_xlsx_head( __( 'Orders fulfilled', 'fleuriste' ), $period ), 'payment', 'net' );

	// By driver, with formulas.
	if ( $rows_data ) {
		$first = 5;
		$last  = 4 + count( $rows_data );
		$rows[] = array();
		$rows[] = array(
			array(
				'v' => __( 'By driver', 'fleuriste' ),
				's' => Fleuriste_XLSX::BOLD,
			),
			array(
				'v' => __( 'Deliveries', 'fleuriste' ),
				's' => Fleuriste_XLSX::BOLD,
			),
			array(
				'v' => __( 'Received', 'fleuriste' ),
				's' => Fleuriste_XLSX::BOLD,
			),
		);
		$names = array_unique( wp_list_pluck( $rows_data, 'driver' ) );
		sort( $names );
		foreach ( $names as $name ) {
			$r      = count( $rows ) + 1;
			$subset = array_filter(
				$rows_data,
				function ( $row ) use ( $name ) {
					return $row['driver'] === $name;
				}
			);
			$rows[] = array(
				'' !== $name ? $name : __( '(no driver)', 'fleuriste' ),
				array(
					'v' => count( $subset ),
					'f' => '' !== $name ? "COUNTIF(J{$first}:J{$last},A{$r})" : "COUNTBLANK(J{$first}:J{$last})",
				),
				array(
					'v' => fleuriste_report_sum( $subset, 'net' ),
					'f' => '' !== $name ? "SUMIF(J{$first}:J{$last},A{$r},N{$first}:N{$last})" : "SUMIF(J{$first}:J{$last},\"\",N{$first}:N{$last})",
					's' => Fleuriste_XLSX::MONEY,
				),
			);
		}
	}
	$x->add_sheet( __( 'Orders fulfilled', 'fleuriste' ), $rows, $w, 4 );
	list( $rows, $w ) = fleuriste_report_xlsx_items( $rows_data, $period, fleuriste_report_xlsx_head( __( 'Products delivered', 'fleuriste' ), $period ) );
	$x->add_sheet( __( 'Products', 'fleuriste' ), $rows, $w, 4 );
	return $x->build();
}

/* -------------------------------------------------------------------------
 * PDF
 * ---------------------------------------------------------------------- */

/**
 * Draw a report into a PDF: header on each page, a table that runs over pages,
 * totals, and summary blocks.
 *
 * @param Fleuriste_PDF $pdf      PDF.
 * @param string        $title    Report title.
 * @param array         $period   Period.
 * @param array         $cols     [label, key, width, align(L|R), type(text|money|date|datetime)].
 * @param array         $rows     Rows.
 * @param array         $totals   key => amount, shown in the total row.
 * @param float         $y        Start y (0 = new page).
 * @param string        $heading  Optional section heading above the table.
 * @return float Y after the table.
 */
function fleuriste_report_pdf_table( $pdf, $title, $period, $cols, $rows, $totals, $y = 0, $heading = '' ) {
	$left   = 36;
	$right  = $pdf->w - 36;
	$bottom = $pdf->h - 48;
	$rh     = 17;
	$fs     = 8.2;
	$page   = function () use ( $pdf, $title, $period, $left, $right ) {
		$pdf->add_page();
		$pdf->text( $left, 46, get_bloginfo( 'name' ), 9, true, '#9E4F63' );
		$pdf->text( $left, 66, $title . ' – ' . $period['label'], 15, true );
		$pdf->text( $right, 66, sprintf( /* translators: 1: from, 2: to */ __( '%1$s to %2$s', 'fleuriste' ), wp_date( 'd/m/Y', $period['start'] ), wp_date( 'd/m/Y', $period['end'] ) ), 9, false, '#6F6266', 'R' );
		$pdf->line( $left, 76, $right, 76, '#9E4F63', 0.8 );
		$pdf->text( $left, $pdf->h - 24, get_bloginfo( 'name' ) . ' · ' . $title . ' · ' . sprintf( /* translators: %s: date */ __( 'created %s', 'fleuriste' ), wp_date( 'd/m/Y H:i' ) ), 7.5, false, '#8A7D81' );
		$pdf->text( $right, $pdf->h - 24, sprintf( /* translators: %s: page number */ __( 'Page %s', 'fleuriste' ), $pdf->page_count() . ' / {nb}' ), 7.5, false, '#8A7D81', 'R' );
		return 92;
	};
	$header = function ( $y ) use ( $pdf, $cols, $left, $right, $rh ) {
		$pdf->box( $left, $y, $right - $left, $rh + 1, '#9E4F63' );
		$x = $left;
		foreach ( $cols as $c ) {
			$tx = 'R' === $c[3] ? $x + $c[2] - 5 : $x + 5;
			$pdf->text( $tx, $y + 12, $pdf->fit( $c[0], $c[2] - 8, 7.8, true ), 7.8, true, '#FFFFFF', $c[3] );
			$x += $c[2];
		}
		return $y + $rh + 1;
	};
	$cell = function ( $c, $v ) {
		switch ( $c[4] ) {
			case 'money':
				return fleuriste_report_money( $v );
			case 'date':
				return $v ? ( is_numeric( $v ) ? wp_date( 'd/m/Y', $v ) : date_i18n( 'd/m/Y', strtotime( $v ) ) ) : '';
			case 'datetime':
				return $v ? wp_date( 'd/m H:i', $v ) : '';
			default:
				return (string) $v;
		}
	};

	if ( ! $y ) {
		$y = $page();
	}
	if ( $heading ) {
		if ( $y + 60 > $bottom ) {
			$y = $page();
		}
		$pdf->text( $left, $y + 14, $heading, 11.5, true );
		$y += 24;
	}
	$y = $header( $y );
	if ( ! $rows ) {
		$pdf->text( $left + 5, $y + 14, __( 'No orders in this period.', 'fleuriste' ), $fs, false, '#6F6266' );
		return $y + $rh + 10;
	}
	foreach ( $rows as $i => $r ) {
		if ( $y + $rh > $bottom ) {
			$y = $header( $page() );
		}
		if ( $i % 2 ) {
			$pdf->box( $left, $y, $right - $left, $rh, '#FBF6F7' );
		}
		$x = $left;
		foreach ( $cols as $c ) {
			$text = $pdf->fit( $cell( $c, $r[ $c[1] ] ), $c[2] - 9, $fs );
			$pdf->text( 'R' === $c[3] ? $x + $c[2] - 5 : $x + 5, $y + 11.5, $text, $fs, false, '#2B1D21', $c[3] );
			$x += $c[2];
		}
		$y += $rh;
	}
	// Total row.
	if ( $y + $rh + 6 > $bottom ) {
		$y = $header( $page() );
	}
	$pdf->line( $left, $y + 0.5, $right, $y + 0.5, '#9E4F63', 0.8 );
	$x = $left;
	foreach ( $cols as $i => $c ) {
		if ( 0 === $i ) {
			$pdf->text( $x + 5, $y + 13, __( 'Total', 'fleuriste' ), 8.6, true );
		} elseif ( 1 === $i ) {
			$pdf->text( $x + 5, $y + 13, sprintf( /* translators: %d: number of orders */ _n( '%d order', '%d orders', count( $rows ), 'fleuriste' ), count( $rows ) ), 8.6, true );
		} elseif ( isset( $totals[ $c[1] ] ) ) {
			$pdf->text( $x + $c[2] - 5, $y + 13, fleuriste_report_money( $totals[ $c[1] ] ), 8.6, true, '#2B1D21', 'R' );
		}
		$x += $c[2];
	}
	return $y + $rh + 8;
}

/**
 * Small two-column summary box (label → value) in the PDF.
 *
 * @param Fleuriste_PDF $pdf   PDF.
 * @param float         $y     Y.
 * @param string        $title Title.
 * @param array         $lines [label, value, bold?].
 * @param callable      $page  New-page callback (returns y).
 * @return float
 */
function fleuriste_report_pdf_summary( $pdf, $y, $title, $lines, $page ) {
	$left   = 36;
	$width  = 330;
	$height = 26 + count( $lines ) * 16;
	if ( $y + $height > $pdf->h - 48 ) {
		$y = $page();
	}
	$pdf->box( $left, $y, $width, $height, '#F6EDEE' );
	$pdf->text( $left + 12, $y + 17, $title, 9.5, true );
	$yy = $y + 34;
	foreach ( $lines as $l ) {
		$bold = ! empty( $l[2] );
		$pdf->text( $left + 12, $yy, $pdf->fit( $l[0], 200, 8.6, $bold ), 8.6, $bold );
		$pdf->text( $left + $width - 12, $yy, $l[1], 8.6, $bold, '#2B1D21', 'R' );
		$yy += 16;
	}
	return $y + $height + 12;
}

/**
 * Orders received → PDF.
 *
 * @param array $period Period.
 * @return string
 */
function fleuriste_report_received_pdf( $period ) {
	$data  = fleuriste_report_received( $period['start'], $period['end'] );
	$pdf   = new Fleuriste_PDF();
	$title = __( 'Orders received', 'fleuriste' );
	$cols  = array(
		array( __( 'Order', 'fleuriste' ), 'number', 44, 'L', 'text' ),
		array( __( 'Placed', 'fleuriste' ), 'created', 62, 'L', 'datetime' ),
		array( __( 'Customer', 'fleuriste' ), 'customer', 104, 'L', 'text' ),
		array( __( 'Recipient', 'fleuriste' ), 'recipient', 96, 'L', 'text' ),
		array( __( 'Delivery', 'fleuriste' ), 'delivery', 56, 'L', 'date' ),
		array( __( 'Items', 'fleuriste' ), 'items', 170, 'L', 'text' ),
		array( __( 'Payment', 'fleuriste' ), 'payment', 92, 'L', 'text' ),
		array( __( 'Status', 'fleuriste' ), 'status', 72, 'L', 'text' ),
		array( __( 'Amount', 'fleuriste' ), 'total', 74, 'R', 'money' ),
	);
	$total = fleuriste_report_sum( $data['orders'], 'total' );
	$y     = fleuriste_report_pdf_table( $pdf, $title, $period, $cols, $data['orders'], array( 'total' => $total ) );

	$new_page = function () use ( $pdf ) {
		$pdf->add_page();
		return 50;
	};
	$lines    = array(
		array( __( 'Orders received', 'fleuriste' ), number_format_i18n( count( $data['orders'] ) ) ),
		array( __( 'Total you should get', 'fleuriste' ), fleuriste_report_money( $total ), true ),
	);
	foreach ( fleuriste_report_by_payment( $data['orders'] ) as $method => $d ) {
		$lines[] = array( $method . ' (' . $d[0] . ')', fleuriste_report_money( $d[1] ) );
	}
	$paid    = array_filter( $data['orders'], function ( $r ) { return $r['paid']; } ); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements
	$lines[] = array( __( 'Already paid', 'fleuriste' ), fleuriste_report_money( fleuriste_report_sum( $paid, 'total' ) ) );
	$lines[] = array( __( 'Not paid yet', 'fleuriste' ), fleuriste_report_money( $total - fleuriste_report_sum( $paid, 'total' ) ) );
	$y       = fleuriste_report_pdf_summary( $pdf, $y + 6, __( 'Summary', 'fleuriste' ), $lines, $new_page );

	if ( $data['cancelled'] ) {
		$ccols = array(
			array( __( 'Order', 'fleuriste' ), 'number', 44, 'L', 'text' ),
			array( __( 'Placed', 'fleuriste' ), 'created', 62, 'L', 'datetime' ),
			array( __( 'Customer', 'fleuriste' ), 'customer', 120, 'L', 'text' ),
			array( __( 'Items', 'fleuriste' ), 'items', 230, 'L', 'text' ),
			array( __( 'Payment', 'fleuriste' ), 'payment', 110, 'L', 'text' ),
			array( __( 'Status', 'fleuriste' ), 'status', 130, 'L', 'text' ),
			array( __( 'Amount', 'fleuriste' ), 'total', 74, 'R', 'money' ),
		);
		fleuriste_report_pdf_table( $pdf, $title, $period, $ccols, $data['cancelled'], array( 'total' => fleuriste_report_sum( $data['cancelled'], 'total' ) ), $y + 4, __( 'Cancelled orders (not counted above)', 'fleuriste' ) );
	}
	return $pdf->build( get_bloginfo( 'name' ) . ' – ' . $title . ' – ' . $period['label'] );
}

/**
 * Orders fulfilled → PDF.
 *
 * @param array $period Period.
 * @return string
 */
function fleuriste_report_fulfilled_pdf( $period ) {
	$rows  = fleuriste_report_fulfilled( $period['start'], $period['end'] );
	$pdf   = new Fleuriste_PDF();
	$title = __( 'Orders fulfilled', 'fleuriste' );
	$cols  = array(
		array( __( 'Order', 'fleuriste' ), 'number', 44, 'L', 'text' ),
		array( __( 'Placed', 'fleuriste' ), 'created', 54, 'L', 'date' ),
		array( __( 'Delivered', 'fleuriste' ), 'completed', 62, 'L', 'datetime' ),
		array( __( 'Customer', 'fleuriste' ), 'customer', 100, 'L', 'text' ),
		array( __( 'Items', 'fleuriste' ), 'items', 168, 'L', 'text' ),
		array( __( 'Payment', 'fleuriste' ), 'payment', 88, 'L', 'text' ),
		array( __( 'Driver', 'fleuriste' ), 'driver', 70, 'L', 'text' ),
		array( __( 'Amount', 'fleuriste' ), 'total', 64, 'R', 'money' ),
		array( __( 'Refunded', 'fleuriste' ), 'refunded', 56, 'R', 'money' ),
		array( __( 'Received', 'fleuriste' ), 'net', 64, 'R', 'money' ),
	);
	$net   = fleuriste_report_sum( $rows, 'net' );
	$y     = fleuriste_report_pdf_table(
		$pdf,
		$title,
		$period,
		$cols,
		$rows,
		array(
			'total'    => fleuriste_report_sum( $rows, 'total' ),
			'refunded' => fleuriste_report_sum( $rows, 'refunded' ),
			'net'      => $net,
		)
	);
	$new_page = function () use ( $pdf ) {
		$pdf->add_page();
		return 50;
	};
	$lines    = array(
		array( __( 'Orders delivered', 'fleuriste' ), number_format_i18n( count( $rows ) ) ),
		array( __( 'Total you got', 'fleuriste' ), fleuriste_report_money( $net ), true ),
	);
	foreach ( fleuriste_report_by_payment( $rows, 'net' ) as $method => $d ) {
		$lines[] = array( $method . ' (' . $d[0] . ')', fleuriste_report_money( $d[1] ) );
	}
	$y = fleuriste_report_pdf_summary( $pdf, $y + 6, __( 'Summary', 'fleuriste' ), $lines, $new_page );

	$drivers = array();
	foreach ( $rows as $r ) {
		$name = '' !== $r['driver'] ? $r['driver'] : __( '(no driver)', 'fleuriste' );
		if ( ! isset( $drivers[ $name ] ) ) {
			$drivers[ $name ] = array( 0, 0.0 );
		}
		++$drivers[ $name ][0];
		$drivers[ $name ][1] += $r['net'];
	}
	if ( $drivers ) {
		ksort( $drivers );
		$lines = array();
		foreach ( $drivers as $name => $d ) {
			$lines[] = array( $name . ' (' . $d[0] . ')', fleuriste_report_money( $d[1] ) );
		}
		fleuriste_report_pdf_summary( $pdf, $y, __( 'By driver', 'fleuriste' ), $lines, $new_page );
	}
	return $pdf->build( get_bloginfo( 'name' ) . ' – ' . $title . ' – ' . $period['label'] );
}
