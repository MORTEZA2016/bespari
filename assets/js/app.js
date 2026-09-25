/**
 * اپ مستقل بسپاری — SPA (لاگین توکنی، روتر، نماها، POS).
 */
(function () {
	'use strict';

	var CFG = window.BP_CONFIG || {};
	var TOKEN_KEY = 'bespari_app_token';

	var state = {
		token: null,
		user: null,
		view: 'dashboard',
		page: 1
	};

	var el = document.getElementById( 'bp-app' );

	function fmt( n ) {
		try {
			return new Intl.NumberFormat( 'fa-IR' ).format( Math.round( Number( n ) || 0 ) );
		} catch ( e ) {
			return String( n );
		}
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = String( s );
		return d.innerHTML;
	}

	function getToken() {
		try { return localStorage.getItem( TOKEN_KEY ) || ''; } catch ( e ) { return ''; }
	}
	function setToken( t ) {
		try { localStorage.setItem( TOKEN_KEY, t ); } catch ( e ) {}
	}
	function clearToken() {
		try { localStorage.removeItem( TOKEN_KEY ); } catch ( e ) {}
	}

	/* ---------- ارتباط با سرور ---------- */

	function api( path, options ) {
		options = options || {};
		var url = CFG.restUrl + path.replace( /^\//, '' );
		var headers = { 'Content-Type': 'application/json' };
		var token = getToken();
		if ( token ) {
			headers['Authorization'] = 'Bearer ' + token;
		}

		return fetch( url, {
			method: options.method || 'GET',
			headers: headers,
			body: options.body ? JSON.stringify( options.body ) : undefined
		} ).then( function ( res ) {
			return res.json().then( function ( json ) {
				if ( ! res.ok ) {
					var msg = ( json && json.message ) || 'خطای ارتباط با سرور';
					var err = new Error( msg );
					err.status = res.status;
					err.data = json;
					throw err;
				}
				return json;
			} );
		} );
	}

	/* ---------- لاگین ---------- */

	function renderLogin( errorMsg ) {
		el.innerHTML =
			'<div class="bp-login-wrap">' +
				'<div class="bp-login">' +
					'<div class="bp-login__logo">بسپاری <span>ERP</span></div>' +
					'<p class="bp-login__desc">پنل مدیریت فروش چندکاناله</p>' +
					( errorMsg ? '<div class="bp-alert bp-alert--error">' + esc( errorMsg ) + '</div>' : '' ) +
					'<form id="bp-login-form">' +
						'<label class="bp-field"><span>نام کاربری یا ایمیل</span>' +
							'<input type="text" name="username" required autocomplete="username" /></label>' +
						'<label class="bp-field"><span>گذرواژه</span>' +
							'<input type="password" name="password" required autocomplete="current-password" /></label>' +
						'<button type="submit" class="bp-btn bp-btn--primary bp-btn--block">ورود به پنل</button>' +
					'</form>' +
				'</div>' +
			'</div>';

		var form = document.getElementById( 'bp-login-form' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( 'button[type="submit"]' );
			btn.disabled = true;
			btn.textContent = 'در حال ورود…';

			api( 'auth/login', {
				method: 'POST',
				body: {
					username: form.username.value.trim(),
					password: form.password.value
				}
			} ).then( function ( res ) {
				if ( res && res.ok && res.token ) {
					setToken( res.token );
					state.token = res.token;
					state.user = res.user;
					renderApp();
				} else {
					renderLogin( ( res && res.message ) || 'ورود ناموفق بود.' );
				}
			} ).catch( function ( err ) {
				renderLogin( err.message );
			} );
		} );
	}

	/* ---------- اسکلت پنل ---------- */

	function renderApp() {
		var user = state.user;
		if ( ! user ) { return; }

		var menus = user.menus || [];
		if ( ! menus.length ) {
			clearToken();
			renderLogin( 'دسترسی به پنل ندارید.' );
			return;
		}

		// view پیش‌فرض.
		var hash = ( location.hash || '' ).replace( /^#\/?/, '' );
		var view = hash.split( '?' )[0];
		if ( ! view || ! menus.some( function ( m ) { return m.key === view; } ) ) {
			view = menus[0].key;
			location.hash = '#/' + view;
		}
		state.view = view;

		el.innerHTML =
			'<div class="bp-shell">' +
				'<aside class="bp-sidebar">' +
					'<div class="bp-sidebar__logo">بسپاری <span>ERP</span></div>' +
					'<nav class="bp-nav" id="bp-nav"></nav>' +
					'<div class="bp-sidebar__user">' +
						'<div class="bp-avatar">' + esc( ( user.name || '?' ).charAt( 0 ) ) + '</div>' +
						'<div><div class="bp-uname">' + esc( user.name ) + '</div>' +
						'<div class="bp-urole">' + esc( user.role ) + '</div></div>' +
					'</div>' +
				'</aside>' +
				'<main class="bp-main">' +
					'<header class="bp-topbar">' +
						'<h1 class="bp-topbar__title" id="bp-view-title"></h1>' +
						'<div style="display:flex;gap:8px;align-items:center;">' +
							'<div class="bp-bell" id="bp-bell"></div>' +
							'<button class="bp-btn bp-nav__item--logout" id="bp-logout">خروج</button>' +
						'</div>' +
					'</header>' +
					'<div class="bp-content" id="bp-content"></div>' +
				'</main>' +
			'</div>';

		// سایدبار.
		var nav = document.getElementById( 'bp-nav' );
		menus.forEach( function ( m ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'bp-nav__item' + ( m.key === view ? ' is-active' : '' );
			b.dataset.view = m.key;
			b.innerHTML = esc( m.label );
			b.addEventListener( 'click', function () {
				location.hash = '#/' + m.key;
			} );
			nav.appendChild( b );
		} );

		document.getElementById( 'bp-logout' ).addEventListener( 'click', function () {
			api( 'auth/logout', { method: 'POST' } ).catch( function () {} ).finally( function () {
				clearToken();
				state.token = null;
				state.user = null;
				renderLogin( '' );
			} );
		} );

		// زنگوله اعلان + به‌روزرسانی دوره‌ای.
		renderBell();
		setInterval( function () {
			if ( state.user && document.getElementById( 'bp-bell' ) ) {
				renderBell();
			}
		}, 60000 );

		loadView( view );
	}

	/* ---------- روتر ---------- */

	function currentView() {
		var hash = ( location.hash || '' ).replace( /^#\/?/, '' );
		return hash.split( '?' )[0] || 'dashboard';
	}

	window.addEventListener( 'hashchange', function () {
		var view = currentView();
		var menus = ( state.user && state.user.menus ) || [];
		if ( ! state.user || ! menus.some( function ( m ) { return m.key === view; } ) ) {
			return;
		}
		state.view = view;
		// به‌روزرسانی سایدبار.
		var nav = document.getElementById( 'bp-nav' );
		if ( nav ) {
			Array.prototype.forEach.call( nav.querySelectorAll( '.bp-nav__item' ), function ( b ) {
				b.classList.toggle( 'is-active', b.dataset.view === view );
			} );
		}
		loadView( view );
	} );

	/* ---------- بارگذاری نماها ---------- */

	function loadView( view ) {
		var content = document.getElementById( 'bp-content' );
		var title = document.getElementById( 'bp-view-title' );
		if ( ! content ) { return; }

		var menus = ( state.user && state.user.menus ) || [];
		var m = menus.filter( function ( x ) { return x.key === view; } )[0];
		if ( title ) { title.textContent = m ? m.label : ''; }

		if ( view === 'pos' ) {
			renderPos( content );
			return;
		}

		if ( view === 'production' ) {
			renderProduction( content );
			return;
		}

		if ( ERP_VIEWS[ view ] ) {
			renderCrud( content, view );
			return;
		}

		content.innerHTML = '<div class="bp-boot">در حال بارگذاری…</div>';

		var query = 'page=' + state.page;
		api( 'panel/' + view + '?' + query ).then( function ( res ) {
			renderTable( content, res );
		} ).catch( function ( err ) {
			if ( 401 === err.status ) {
				clearToken();
				state.token = null;
				state.user = null;
				renderLogin( 'نشست شما پایان یافته است. دوباره وارد شوید.' );
			} else {
				content.innerHTML = '<div class="bp-alert bp-alert--error">' + esc( err.message ) + '</div>';
			}
		} );
	}

	function renderTable( content, res ) {
		var html = '';

		if ( res && res.stats && res.stats.length ) {
			html += '<div class="bp-stats">';
			res.stats.forEach( function ( s ) {
				html += '<div class="bp-stat"><div class="bp-stat__value">' + esc( s.value ) + '</div>' +
					'<div class="bp-stat__label">' + esc( s.label ) + '</div></div>';
			} );
			html += '</div>';
		}

		if ( res && res.section ) {
			html += '<h2 class="bp-section">' + esc( res.section ) + '</h2>';
		}

		if ( res && res.pages && res.pages > 1 ) {
			html += '<div class="bp-pagination">';
			for ( var i = 1; i <= Math.min( res.pages, 15 ); i++ ) {
				html += '<button class="bp-page' + ( i === ( res.page || 1 ) ? ' is-current' : '' ) +
					'" data-page="' + i + '">' + fmt( i ) + '</button>';
			}
			html += '</div>';
		}

		var headers = ( res && res.headers ) || [];
		var rows = ( res && res.rows ) || [];

		html += '<div class="bp-card"><table class="bp-table"><thead><tr>';
		headers.forEach( function ( h ) { html += '<th>' + esc( h ) + '</th>'; } );
		html += '</tr></thead><tbody>';
		if ( ! rows.length ) {
			html += '<tr><td class="bp-empty" colspan="' + headers.length + '">موردی یافت نشد.</td></tr>';
		} else {
			rows.forEach( function ( row ) {
				html += '<tr>';
				row.forEach( function ( cell ) { html += '<td>' + cell + '</td>'; } );
				html += '</tr>';
			} );
		}
		html += '</tbody></table></div>';

		content.innerHTML = html;

		// کلیک صفحه‌بندی.
		content.querySelectorAll( '.bp-page' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				state.page = parseInt( b.dataset.page, 10 ) || 1;
				loadView( state.view );
			} );
		} );

		// دکمه ارسال سفارش (بازاریاب، سفارش‌های تولیدشده).
		content.querySelectorAll( '.bp-ship-btn' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = parseInt( b.dataset.id, 10 ) || 0;
				b.disabled = true;
				b.textContent = 'در حال ثبت…';

				api( 'production/ship', { method: 'POST', body: { order_id: id } } )
					.then( function () { loadView( state.view ); } )
					.catch( function ( err ) {
						b.disabled = false;
						b.textContent = 'ارسال شد';
						var msg = document.createElement( 'div' );
						msg.className = 'bp-alert bp-alert--error';
						msg.style.marginBottom = '8px';
						msg.textContent = err.message;
						content.prepend( msg );
					} );
			} );
		} );
	}

	/* ---------- POS ---------- */

	var cart = {};
	var channels = {};
	var products = [];
	var calcTimer = null;
	var searchTimer = null;

	function cartItems() {
		return Object.keys( cart ).map( function ( id ) {
			var c = cart[id];
			return {
				product_id: c.product.id,
				quantity: c.qty,
				unit_price_override: c.override
			};
		} );
	}

	function renderPos( content ) {
		cart = {};
		channels = {};
		products = [];

		content.innerHTML =
			'<div class="bp-pos">' +
				'<div>' +
					'<div class="bp-pos__search">' +
						'<input type="search" id="bp-pos-search" placeholder="جستجوی محصول (نام، کد، SKU)..." />' +
					'</div>' +
					'<div class="bp-product-grid" id="bp-pos-grid"><div class="bp-boot">در حال بارگذاری محصولات…</div></div>' +
				'</div>' +
				'<div class="bp-cart">' +
					'<div class="bp-cart__title">سبد سفارش</div>' +
					'<table><thead><tr><th>محصول</th><th>تعداد</th><th>قیمت</th><th></th></tr></thead>' +
						'<tbody id="bp-cart-body"><tr><td class="bp-cart__empty" colspan="4">محصولی اضافه نشده است.</td></tr></tbody></table>' +
					'<div class="bp-totals" id="bp-totals">' +
						'<div class="bp-totals__row"><span>جمع کل:</span><strong id="bp-total-gross">۰</strong></div>' +
						'<div class="bp-totals__row"><span>کسورات:</span><span id="bp-total-ded">۰</span></div>' +
						'<div class="bp-totals__row bp-totals__row--net"><span>خالص دریافتی:</span><strong id="bp-total-net">۰</strong></div>' +
					'</div>' +
					'<div id="bp-checkout" hidden>' +
						'<label class="bp-field"><span>نوع فروش</span>' +
							'<select id="bp-sale-type"><option value="cash">نقدی</option><option value="credit">اعتباری</option></select></label>' +
						'<label class="bp-field"><span>کانال فروش</span><select id="bp-channel"></select></label>' +
						'<div id="bp-customer-fields" hidden>' +
							'<label class="bp-field"><span>نام مشتری *</span><input type="text" id="bp-customer-name" /></label>' +
							'<label class="bp-field"><span>تلفن مشتری *</span><input type="tel" id="bp-customer-phone" dir="ltr" /></label>' +
							'<label class="bp-field"><span>آدرس مشتری</span><textarea id="bp-customer-address" rows="2"></textarea></label>' +
						'</div>' +
						'<label class="bp-field"><span>توضیحات</span><textarea id="bp-notes" rows="2"></textarea></label>' +
						'<button type="button" class="bp-btn bp-btn--primary bp-btn--block" id="bp-submit-order">ثبت سفارش</button>' +
						'<div id="bp-pos-result"></div>' +
					'</div>' +
				'</div>' +
			'</div>';

		var search = document.getElementById( 'bp-pos-search' );
		search.addEventListener( 'input', function () {
			clearTimeout( searchTimer );
			searchTimer = setTimeout( function () { loadProducts( search.value.trim() ); }, 300 );
		} );

		document.getElementById( 'bp-channel' ).addEventListener( 'change', onChannelChange );
		document.getElementById( 'bp-sale-type' ).addEventListener( 'change', scheduleCalc );
		document.getElementById( 'bp-submit-order' ).addEventListener( 'click', submitOrder );

		loadProducts( '' );
		loadChannels();
	}

	function loadProducts( search ) {
		var grid = document.getElementById( 'bp-pos-grid' );
		if ( ! grid ) { return; }
		grid.innerHTML = '<div class="bp-boot">در حال بارگذاری…</div>';

		api( 'pos/products?s=' + encodeURIComponent( search ) + '&limit=50' ).then( function ( res ) {
			products = res.products || [];
			grid.innerHTML = '';
			if ( ! products.length ) {
				grid.innerHTML = '<div class="bp-boot">محصولی یافت نشد.</div>';
				return;
			}
			products.forEach( function ( p ) {
				var card = document.createElement( 'button' );
				card.type = 'button';
				card.className = 'bp-product-card' + ( cart[p.id] ? ' is-added' : '' );
				card.dataset.productId = p.id;
				card.innerHTML =
					'<span class="bp-product-card__name">' + esc( p.name ) + '</span>' +
					'<span class="bp-product-card__price">' + fmt( p.base_price ) + ' تومان</span>' +
					'<span class="bp-product-card__stock">موجودی: ' + fmt( p.stock ) + '</span>';
				card.addEventListener( 'click', function () { addToCart( p ); } );
				grid.appendChild( card );
			} );
		} ).catch( function () {
			grid.innerHTML = '<div class="bp-boot">خطا در بارگذاری محصولات.</div>';
		} );
	}

	function loadChannels() {
		api( 'pos/channels' ).then( function ( res ) {
			var sel = document.getElementById( 'bp-channel' );
			if ( ! sel ) { return; }
			( res.channels || [] ).forEach( function ( ch ) {
				channels[ch.id] = ch;
				var opt = document.createElement( 'option' );
				opt.value = ch.id;
				opt.textContent = ch.name;
				sel.appendChild( opt );
			} );
			onChannelChange();
		} );
	}

	function addToCart( p ) {
		if ( cart[p.id] ) {
			cart[p.id].qty += 1;
		} else {
			cart[p.id] = { product: p, qty: 1, override: 0 };
		}
		renderCart();
		scheduleCalc();
	}

	function setQty( id, qty ) {
		if ( ! cart[id] ) { return; }
		qty = parseInt( qty, 10 );
		if ( isNaN( qty ) || qty < 1 ) { qty = 1; }
		cart[id].qty = qty;
		renderCart();
		scheduleCalc();
	}

	function setOverride( id, value ) {
		if ( ! cart[id] ) { return; }
		var v = parseFloat( String( value ).replace( /[^\d.]/g, '' ) );
		cart[id].override = ( isNaN( v ) || v <= 0 ) ? 0 : v;
		scheduleCalc();
	}

	function renderCart() {
		var body = document.getElementById( 'bp-cart-body' );
		var totals = document.getElementById( 'bp-totals' );
		var checkout = document.getElementById( 'bp-checkout' );
		var grid = document.getElementById( 'bp-pos-grid' );
		if ( ! body ) { return; }

		var ids = Object.keys( cart );
		body.innerHTML = '';

		if ( ! ids.length ) {
			body.innerHTML = '<tr><td class="bp-cart__empty" colspan="4">محصولی اضافه نشده است.</td></tr>';
			totals.classList.remove( 'is-visible' );
			checkout.hidden = true;
		} else {
			ids.forEach( function ( id ) {
				var c = cart[id];
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td>' + esc( c.product.name ) + '</td>' +
					'<td><span class="bp-qty">' +
						'<button type="button" data-act="minus">−</button>' +
						'<input type="number" min="1" value="' + c.qty + '" data-act="qty" />' +
						'<button type="button" data-act="plus">+</button></span></td>' +
					'<td><input class="bp-price-in' + ( c.override > 0 ? ' is-edited' : '' ) + '" type="number" min="0" step="any" ' +
						'value="' + ( c.override > 0 ? c.override : '' ) + '" placeholder="' + fmt( c.product.base_price ) + '" data-act="price" /></td>' +
					'<td><button class="bp-rm" type="button" data-act="remove">✕</button></td>';

				tr.querySelectorAll( '[data-act]' ).forEach( function ( node ) {
					var act = node.dataset.act;
					if ( 'qty' === act ) {
						node.addEventListener( 'change', function () { setQty( id, node.value ); } );
					} else if ( 'price' === act ) {
						node.addEventListener( 'input', function () {
							node.classList.toggle( 'is-edited', node.value !== '' );
							setOverride( id, node.value );
						} );
					} else if ( 'minus' === act ) {
						node.addEventListener( 'click', function () { setQty( id, c.qty - 1 ); } );
					} else if ( 'plus' === act ) {
						node.addEventListener( 'click', function () { setQty( id, c.qty + 1 ); } );
					} else if ( 'remove' === act ) {
						node.addEventListener( 'click', function () {
							delete cart[id];
							renderCart();
							scheduleCalc();
						} );
					}
				} );

				body.appendChild( tr );
			} );
			totals.classList.add( 'is-visible' );
			checkout.hidden = false;
		}

		if ( grid ) {
			grid.querySelectorAll( '.bp-product-card' ).forEach( function ( card ) {
				card.classList.toggle( 'is-added', !! cart[card.dataset.productId] );
			} );
		}
	}

	function scheduleCalc() {
		clearTimeout( calcTimer );
		calcTimer = setTimeout( recalc, 350 );
	}

	function recalc() {
		var grossEl = document.getElementById( 'bp-total-gross' );
		var dedEl = document.getElementById( 'bp-total-ded' );
		var netEl = document.getElementById( 'bp-total-net' );
		if ( ! grossEl ) { return; }

		if ( ! Object.keys( cart ).length ) {
			grossEl.textContent = dedEl.textContent = netEl.textContent = '۰';
			return;
		}

		var channelId = parseInt( ( document.getElementById( 'bp-channel' ) || {} ).value, 10 ) || 0;
		if ( ! channelId ) { return; }

		api( 'pos/calculate', {
			method: 'POST',
			body: {
				channel_id: channelId,
				sale_type: ( document.getElementById( 'bp-sale-type' ) || {} ).value || 'cash',
				seller_id: 0,
				items: cartItems()
			}
		} ).then( function ( res ) {
			grossEl.textContent = fmt( res.gross );
			dedEl.textContent = fmt( res.deductions );
			netEl.textContent = fmt( res.net );
		} ).catch( function () {} );
	}

	function onChannelChange() {
		var sel = document.getElementById( 'bp-channel' );
		var cust = document.getElementById( 'bp-customer-fields' );
		if ( ! sel || ! cust ) { return; }

		var ch = channels[ parseInt( sel.value, 10 ) ];
		cust.hidden = ! ( ch && ch.needs_customer );
		scheduleCalc();
	}

	function submitOrder() {
		var result = document.getElementById( 'bp-pos-result' );
		var btn = document.getElementById( 'bp-submit-order' );

		if ( ! Object.keys( cart ).length ) {
			result.className = 'bp-alert bp-alert--error';
			result.textContent = 'ابتدا محصول اضافه کنید.';
			return;
		}

		var sel = document.getElementById( 'bp-channel' );
		var channelId = parseInt( sel.value, 10 ) || 0;
		var ch = channels[ channelId ];
		var needsCustomer = !!( ch && ch.needs_customer );

		var nameEl = document.getElementById( 'bp-customer-name' );
		var phoneEl = document.getElementById( 'bp-customer-phone' );

		if ( needsCustomer && ( ! nameEl.value.trim() || ! phoneEl.value.trim() ) ) {
			result.className = 'bp-alert bp-alert--error';
			result.textContent = 'برای کانال «فاکتور به فاکتور» نام و تلفن مشتری الزامی است.';
			return;
		}

		btn.disabled = true;
		btn.textContent = 'در حال ثبت…';

		var body = {
			channel_id: channelId,
			sale_type: document.getElementById( 'bp-sale-type' ).value,
			seller_id: 0,
			items: cartItems(),
			notes: ( document.getElementById( 'bp-notes' ) || {} ).value || ''
		};

		if ( needsCustomer ) {
			body.customer_name = nameEl.value.trim();
			body.customer_phone = phoneEl.value.trim();
			body.customer_address = ( document.getElementById( 'bp-customer-address' ) || {} ).value.trim() || '';
		}

		api( 'pos/order', { method: 'POST', body: body } ).then( function ( res ) {
			result.className = 'bp-alert';
			result.style.background = 'var(--bp-success-subtle)';
			result.style.borderColor = 'var(--bp-success-border)';
			result.style.color = '#003d29';
			result.textContent = 'سفارش ' + ( res.order_number || '' ) + ' با موفقیت ثبت شد.';
			cart = {};
			renderCart();
			recalc();
		} ).catch( function ( err ) {
			result.className = 'bp-alert bp-alert--error';
			result.style.background = '';
			result.style.borderColor = '';
			result.style.color = '';
			result.textContent = err.message;
		} ).finally( function () {
			btn.disabled = false;
			btn.textContent = 'ثبت سفارش';
		} );
	}

	/* ---------- خط تولید ---------- */

	var production = { date: '', brand: 0, channel: 0 };

	function renderProduction( content ) {
		content.innerHTML =
			'<div class="bp-production">' +
				'<div class="bp-datebar">' +
					'<button type="button" class="bp-btn bp-btn--sm" id="bp-prod-prev">‹ روز قبل</button>' +
					'<input type="date" id="bp-prod-date" value="' + esc( production.date ) + '" />' +
					'<button type="button" class="bp-btn bp-btn--sm" id="bp-prod-next">روز بعد ›</button>' +
					'<button type="button" class="bp-btn bp-btn--sm" id="bp-prod-today">امروز</button>' +
					'<span class="bp-datebar__label" id="bp-prod-label"></span>' +
				'</div>' +
				'<div class="bp-alert" id="bp-prod-alert" hidden></div>' +
				'<div class="bp-prod-body" id="bp-prod-body"><div class="bp-boot">در حال بارگذاری…</div></div>' +
			'</div>';

		var dateInput = document.getElementById( 'bp-prod-date' );
		dateInput.addEventListener( 'change', function () {
			loadProductionBody( dateInput.value );
		} );
		document.getElementById( 'bp-prod-prev' ).addEventListener( 'click', function () {
			shiftProductionDate( -1 );
		} );
		document.getElementById( 'bp-prod-next' ).addEventListener( 'click', function () {
			shiftProductionDate( 1 );
		} );
		document.getElementById( 'bp-prod-today' ).addEventListener( 'click', function () {
			loadProductionBody( '' );
		} );

		loadProductionBody( production.date );
	}

	function shiftProductionDate( delta ) {
		var d = production.date ? new Date( production.date + 'T00:00:00' ) : new Date();
		d.setDate( d.getDate() + delta );
		var y = d.getFullYear();
		var m = String( d.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( d.getDate() ).padStart( 2, '0' );
		loadProductionBody( y + '-' + m + '-' + day );
	}

	function prodAlert( message, isError ) {
		var alertEl = document.getElementById( 'bp-prod-alert' );
		if ( ! alertEl ) { return; }
		if ( ! message ) {
			alertEl.hidden = true;
			return;
		}
		alertEl.hidden = false;
		alertEl.className = 'bp-alert' + ( isError ? ' bp-alert--error' : '' );
		alertEl.textContent = message;
	}

	function loadProductionBody( date ) {
		var body = document.getElementById( 'bp-prod-body' );
		var dateInput = document.getElementById( 'bp-prod-date' );
		var label = document.getElementById( 'bp-prod-label' );
		if ( ! body ) { return; }

		prodAlert( '' );
		body.innerHTML = '<div class="bp-boot">در حال بارگذاری…</div>';

		var url = 'production/list';
		if ( date ) {
			url += '?date=' + encodeURIComponent( date );
		}

		api( url ).then( function ( res ) {
			production.date = res.date;
			if ( dateInput ) { dateInput.value = res.date; }
			if ( label ) { label.textContent = toFaDigits( res.date_fa || '' ); }

			var brands = res.brands || [];

			// حفظ برند انتخاب‌شده در صورت وجود.
			if ( ! brands.some( function ( b ) { return b.id === production.brand; } ) ) {
				production.brand = brands.length ? brands[0].id : 0;
			}

			var brand = brands.filter( function ( b ) { return b.id === production.brand; } )[0];
			var channels = ( brand && brand.channels ) || [];

			if ( ! channels.some( function ( c ) { return c.id === production.channel; } ) ) {
				production.channel = channels.length ? channels[0].id : 0;
			}

			renderProductionBody( body, brands, channels );
		} ).catch( function ( err ) {
			if ( 401 === err.status ) {
				clearToken();
				state.token = null;
				state.user = null;
				renderLogin( 'نشست شما پایان یافته است. دوباره وارد شوید.' );
			} else {
				prodAlert( err.message, true );
				body.innerHTML = '';
			}
		} );
	}

	function renderProductionBody( body, brands, channels ) {
		var html = '';

		html += '<div class="bp-tabs">';
		brands.forEach( function ( b ) {
			html += '<button type="button" class="bp-tab' + ( b.id === production.brand ? ' is-active' : '' ) +
				'" data-brand="' + b.id + '">' + esc( b.name ) + '</button>';
		} );
		html += '</div>';

		var brand = brands.filter( function ( b ) { return b.id === production.brand; } )[0];

		if ( ! brand ) {
			html += '<div class="bp-card"><div class="bp-empty">سفارشی برای این روز ثبت نشده است.</div></div>';
			body.innerHTML = html;
			return;
		}

		channels = brand.channels || [];
		html += '<div class="bp-tabs bp-tabs--sub">';
		channels.forEach( function ( c ) {
			html += '<button type="button" class="bp-tab' + ( c.id === production.channel ? ' is-active' : '' ) +
				'" data-channel="' + c.id + '">' + esc( c.name ) +
				( c.shipping_window ? '<span class="bp-chip">' + esc( c.shipping_window ) + '</span>' : '' ) +
				'</button>';
		} );
		html += '</div>';

		var channel = channels.filter( function ( c ) { return c.id === production.channel; } )[0];
		if ( ! channel ) {
			html += '<div class="bp-card"><div class="bp-empty">کانالی موجود نیست.</div></div>';
			body.innerHTML = html;
			bindProductionTabs( body );
			return;
		}

		( channel.groups || [] ).forEach( function ( g, gi ) {
			var open = 0 === gi; // اولین گروه (مهمان یا بالاترین الویت) باز است.
			html += '<div class="bp-accordion' + ( open ? ' is-open' : '' ) + '">' +
				'<button type="button" class="bp-accordion__head" data-group="' + gi + '">' +
					'<span class="bp-accordion__icon">' + ( open ? '▼' : '◀' ) + '</span>' +
					'<span class="bp-accordion__title">' + esc( g.label ) + '</span>' +
					'<span class="bp-accordion__count">' + toFaDigits( String( g.orders.length ) ) + '</span>' +
				'</button>' +
				'<div class="bp-accordion__body"' + ( open ? '' : ' hidden' ) + ' data-body="' + gi + '">';

			if ( ! g.orders.length ) {
				html += '<div class="bp-empty">سفارشی در این گروه نیست.</div>';
			} else {
				g.orders.forEach( function ( o ) {
					html += renderProductionRow( o );
				} );
			}

			html += '</div></div>';
		} );

		body.innerHTML = html;
		bindProductionTabs( body );
		bindProductionActions( body );
	}

	function renderProductionRow( o ) {
		var itemsText = ( o.items || [] ).map( function ( it ) {
			return esc( it.name ) + ' <span class="bp-prod-row__qty">(' + toFaDigits( String( it.quantity ) ) + ')</span>';
		} ).join( '، ' );

		return '<div class="bp-prod-row' + ( o.produced ? ' is-produced' : '' ) + '">' +
			'<div class="bp-prod-row__main">' +
				'<div class="bp-prod-row__num">' + esc( o.order_number ) +
					( o.production_moved ? ' <span class="bp-chip bp-chip--guest">مهمان</span>' : '' ) +
				'</div>' +
				'<div class="bp-prod-row__meta">' +
					'<span>' + esc( o.customer_name ) + '</span>' +
					'<span>بازاریاب: ' + esc( o.seller_name ) + '</span>' +
					'<span>ساعت ثبت: ' + toFaDigits( o.ordered_time || '—' ) + '</span>' +
					'<span class="bp-prod-row__type">' + esc( o.sale_type ) + '</span>' +
				'</div>' +
				'<div class="bp-prod-row__items">' + itemsText + '</div>' +
			'</div>' +
			'<div class="bp-prod-row__side">' +
				'<div class="bp-prod-row__total">' + esc( o.total ) + ' تومان</div>' +
				'<button type="button" class="bp-btn bp-btn--sm ' + ( o.produced ? 'bp-btn--success' : 'bp-btn--primary' ) +
					'" data-act="ready" data-id="' + o.id + '">' +
					( o.produced ? '✓ تولید شد' : 'آماده شد' ) + '</button>' +
				'<span class="bp-prod-row__move">' +
					'<input type="date" data-act="move-date" data-id="' + o.id + '" title="انتقال به روز بعد" />' +
					'<button type="button" class="bp-btn bp-btn--sm" data-act="move" data-id="' + o.id + '">انتقال</button>' +
				'</span>' +
			'</div>' +
		'</div>';
	}

	function bindProductionTabs( body ) {
		body.querySelectorAll( '[data-brand]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				production.brand = parseInt( b.dataset.brand, 10 ) || 0;
				production.channel = 0;
				loadProductionBody( production.date );
			} );
		} );
		body.querySelectorAll( '[data-channel]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				production.channel = parseInt( b.dataset.channel, 10 ) || 0;
				loadProductionBody( production.date );
			} );
		} );
	}

	function bindProductionActions( body ) {
		// اکاردئون‌ها.
		body.querySelectorAll( '.bp-accordion__head' ).forEach( function ( head ) {
			head.addEventListener( 'click', function () {
				var acc = head.closest( '.bp-accordion' );
				var panel = acc.querySelector( '.bp-accordion__body' );
				var icon = head.querySelector( '.bp-accordion__icon' );
				var open = acc.classList.toggle( 'is-open' );
				if ( panel ) { panel.hidden = ! open; }
				if ( icon ) { icon.textContent = open ? '▼' : '◀'; }
			} );
		} );

		// تیک آماده شد.
		body.querySelectorAll( '[data-act="ready"]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var id = parseInt( btn.dataset.id, 10 ) || 0;
				btn.disabled = true;
				btn.textContent = 'در حال ثبت…';

				api( 'production/ready', { method: 'POST', body: { order_id: id } } )
					.then( function () { loadProductionBody( production.date ); } )
					.catch( function ( err ) {
						prodAlert( err.message, true );
						loadProductionBody( production.date );
					} );
			} );
		} );

		// انتقال به روز بعد.
		body.querySelectorAll( '[data-act="move"]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var id = parseInt( btn.dataset.id, 10 ) || 0;
				var wrap = btn.closest( '.bp-prod-row__move' );
				var input = wrap ? wrap.querySelector( '[data-act="move-date"]' ) : null;
				var date = input ? input.value : '';

				if ( ! date ) {
					prodAlert( 'ابتدا تاریخ مقصد را انتخاب کنید.', true );
					return;
				}

				btn.disabled = true;

				api( 'production/move', { method: 'POST', body: { order_id: id, date: date } } )
					.then( function () {
						prodAlert( 'سفارش به ' + toFaDigits( date ) + ' منتقل شد.' );
						loadProductionBody( production.date );
					} )
					.catch( function ( err ) {
						btn.disabled = false;
						prodAlert( err.message, true );
					} );
			} );
		} );
	}

	/* ---------- زنگوله اعلان ---------- */

	var bellOpen = false;

	function toFaDigits( s ) {
		return String( s ).replace( /[0-9]/g, function ( d ) {
			return '۰۱۲۳۴۵۶۷۸۹'[ +d ];
		} );
	}

	function notifTime( iso ) {
		if ( ! iso ) { return ''; }
		var d = new Date( iso.replace( ' ', 'T' ) );
		if ( isNaN( d.getTime() ) ) { return ''; }
		var diff = ( Date.now() - d.getTime() ) / 1000;
		if ( diff < 60 ) { return 'لحظاتی پیش'; }
		if ( diff < 3600 ) { return toFaDigits( Math.floor( diff / 60 ) ) + ' دقیقه پیش'; }
		if ( diff < 86400 ) { return toFaDigits( Math.floor( diff / 3600 ) ) + ' ساعت پیش'; }
		return toFaDigits( Math.floor( diff / 86400 ) ) + ' روز پیش';
	}

	function renderBell() {
		var bell = document.getElementById( 'bp-bell' );
		if ( ! bell ) { return; }

		api( 'notifications/list?limit=20' ).then( function ( res ) {
			var notifs = ( res && res.notifications ) || [];
			var unread = ( res && res.unread ) || 0;

			var html =
				'<button type="button" class="bp-bell__btn" id="bp-bell-btn">🔔' +
					( unread > 0 ? '<span class="bp-bell__count">' + toFaDigits( unread > 99 ? '۹۹+' : unread ) + '</span>' : '' ) +
				'</button>' +
				'<div class="bp-bell__panel" id="bp-bell-panel" hidden>' +
					'<div class="bp-bell__head"><span>اعلان‌ها' +
						( unread > 0 ? ' (' + toFaDigits( unread ) + ' جدید)' : '' ) +
						'</span>' +
						( unread > 0 ? '<button type="button" id="bp-read-all">خواندن همه</button>' : '' ) +
					'</div>' +
					'<div class="bp-bell__list" id="bp-bell-list"></div>' +
				'</div>';

			bell.innerHTML = html;

			var list = document.getElementById( 'bp-bell-list' );
			if ( ! notifs.length ) {
				list.innerHTML = '<div class="bp-notif__empty">اعلانی وجود ندارد.</div>';
			} else {
				notifs.forEach( function ( n ) {
					var item = document.createElement( 'a' );
					item.className = 'bp-notif' + ( n.is_read ? '' : ' is-unread' );
					item.href = n.link || '#';
					if ( ! n.link ) { item.style.cursor = 'default'; }
					item.innerHTML =
						'<div class="bp-notif__title">' + esc( n.title ) + '</div>' +
						( n.body ? '<div class="bp-notif__body">' + esc( n.body ) + '</div>' : '' ) +
						'<div class="bp-notif__time">' + notifTime( n.created_at ) + '</div>';

					item.addEventListener( 'click', function ( e ) {
						if ( n.link ) { return; } // لینک طبیعی باز می‌شود.
						e.preventDefault();
					} );

					// خواندن با کلیک.
					if ( ! n.is_read ) {
						item.addEventListener( 'click', function () {
							api( 'notifications/read', { method: 'POST', body: { id: n.id } } )
								.then( function () { renderBell(); } )
								.catch( function () {} );
						} );
					}

					list.appendChild( item );
				} );
			}

			var panel = document.getElementById( 'bp-bell-panel' );
			var btn = document.getElementById( 'bp-bell-btn' );
			btn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				bellOpen = ! bellOpen;
				panel.hidden = ! bellOpen;
			} );

			var readAll = document.getElementById( 'bp-read-all' );
			if ( readAll ) {
				readAll.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					api( 'notifications/read-all', { method: 'POST' } )
						.then( function () { renderBell(); } )
						.catch( function () {} );
				} );
			}
		} ).catch( function () {} );
	}

	// بستن پنل با کلیک بیرون.
	document.addEventListener( 'click', function ( e ) {
		if ( ! bellOpen ) { return; }
		var bell = document.getElementById( 'bp-bell' );
		if ( bell && ! bell.contains( e.target ) ) {
			bellOpen = false;
			var panel = document.getElementById( 'bp-bell-panel' );
			if ( panel ) { panel.hidden = true; }
		}
	} );

	/* ---------- بوت ---------- */

	function boot() {
		var token = getToken();
		if ( ! token ) {
			renderLogin( '' );
			return;
		}
		state.token = token;
		api( 'auth/me' ).then( function ( res ) {
			if ( res && res.ok && res.user ) {
				state.user = res.user;
				renderApp();
			} else {
				clearToken();
				renderLogin( '' );
			}
		} ).catch( function () {
			clearToken();
			renderLogin( 'نشست شما پایان یافته است. دوباره وارد شوید.' );
		} );
	}

	/* ---------- ERP views (CRUD generic) ---------- */

	// viewهای سرو شده از ErpRestController.
	var ERP_VIEWS = {
		brands: true, pricing: true, settlements: true, payouts: true,
		shipments: true, returns: true, requests: true, accounting: true,
		invoices: true, agents: true, settings: true, seller_dashboard: true,
		users: true
	};

	var crudState = { view: '', filters: {} };

	function crudQuery() {
		var p = [ 'page=' + state.page ];
		Object.keys( crudState.filters ).forEach( function ( k ) {
			var v = crudState.filters[ k ];
			if ( v !== '' && v !== null && v !== undefined ) {
				p.push( encodeURIComponent( k ) + '=' + encodeURIComponent( v ) );
			}
		} );
		return p.join( '&' );
	}

	function renderCrud( content, view ) {
		if ( crudState.view !== view ) {
			crudState.view = view;
			crudState.filters = {};
			state.page = 1;
		}
		content.innerHTML = '<div class="bp-boot">در حال بارگذاری…</div>';

		api( 'erp/' + view + '?' + crudQuery() ).then( function ( res ) {
			renderCrudBody( content, res );
		} ).catch( function ( err ) {
			if ( 401 === err.status ) {
				clearToken();
				state.token = null;
				state.user = null;
				renderLogin( 'نشست شما پایان یافته است. دوباره وارد شوید.' );
			} else {
				content.innerHTML = '<div class="bp-alert bp-alert--error">' + esc( err.message ) + '</div>';
			}
		} );
	}

	function renderCrudBody( content, res ) {
		var html = '';

		// تنظیمات (غیر CRUD).
		if ( res && res.settings ) {
			renderSettingsForm( content, res );
			return;
		}

		if ( res && res.stats && res.stats.length ) {
			html += '<div class="bp-stats">';
			res.stats.forEach( function ( s ) {
				html += '<div class="bp-stat"><div class="bp-stat__value">' + esc( s.value ) + '</div>' +
					'<div class="bp-stat__label">' + esc( s.label ) + '</div></div>';
			} );
			html += '</div>';
		}

		// نوار ابزار.
		var filters = ( res && res.filters ) || [];
		var hasForm = !!( res && res.form );
		var hasPreview = !!( res && res.preview );

		if ( filters.length || hasForm || hasPreview ) {
			html += '<div class="bp-toolbar">';
			html += '<input class="bp-search" data-filter="s" placeholder="جستجو…" value="' + esc( crudState.filters.s || '' ) + '">';
			filters.forEach( function ( f ) {
				html += '<select class="bp-filter" data-filter="' + esc( f.name ) + '">';
				html += '<option value="">' + esc( f.label ) + ': همه</option>';
				( f.options || [] ).forEach( function ( o ) {
					var sel = ( crudState.filters[ f.name ] === String( o.value ) ) ? ' selected' : '';
					html += '<option value="' + esc( o.value ) + '"' + sel + '>' + esc( o.label ) + '</option>';
				} );
				html += '</select>';
			} );
			if ( hasPreview ) {
				html += '<button class="bp-btn bp-btn--ghost" id="bp-preview-btn">پیش‌نمایش قیمت</button>';
			}
			if ( hasForm ) {
				html += '<button class="bp-btn bp-btn--primary" id="bp-add-btn">افزودن</button>';
			}
			html += '</div>';
		}

		var alert = '<div id="bp-crud-alert"></div>';

		var headers = ( res && res.headers ) || [];
		var rows = ( res && res.rows ) || [];
		var actions = ( res && res.rowActions ) || {};
		var actionKeys = Object.keys( actions );

		html += alert;
		html += '<div class="bp-card"><table class="bp-table"><thead><tr>';
		headers.forEach( function ( h ) { html += '<th>' + esc( h ) + '</th>'; } );
		if ( actionKeys.length ) { html += '<th>عملیات</th>'; }
		html += '</tr></thead><tbody>';
		if ( ! rows.length ) {
			var span = headers.length + ( actionKeys.length ? 1 : 0 );
			html += '<tr><td class="bp-empty" colspan="' + span + '">موردی یافت نشد.</td></tr>';
		} else {
			rows.forEach( function ( row ) {
				html += '<tr>';
				row.forEach( function ( cell ) { html += '<td>' + cell + '</td>'; } );
				if ( actionKeys.length ) {
					var id = parseInt( row[0], 10 ) || 0;
					html += '<td class="bp-actions">';
					actionKeys.forEach( function ( key ) {
						var cls = ( 'delete' === key ) ? 'bp-btn--danger' : 'bp-btn--ghost';
						html += '<button class="bp-btn bp-btn--sm ' + cls + '" data-action="' + esc( key ) +
							'" data-id="' + id + '">' + esc( actions[ key ] ) + '</button>';
					} );
					html += '</td>';
				}
				html += '</tr>';
			} );
		}
		html += '</tbody></table></div>';

		if ( res && res.pages && res.pages > 1 ) {
			html += '<div class="bp-pagination">';
			for ( var i = 1; i <= Math.min( res.pages, 15 ); i++ ) {
				html += '<button class="bp-page' + ( i === ( res.page || 1 ) ? ' is-current' : '' ) +
					'" data-page="' + i + '">' + fmt( i ) + '</button>';
			}
			html += '</div>';
		}

		content.innerHTML = html;
		bindCrud( content, res );
	}

	function crudAlert( content, message, isError ) {
		var box = content.querySelector( '#bp-crud-alert' );
		if ( ! box ) { return; }
		box.innerHTML = '<div class="bp-alert ' + ( isError ? 'bp-alert--error' : 'bp-alert--success' ) + '">' +
			esc( message ) + '</div>';
	}

	function bindCrud( content, res ) {
		// جستجو با تأخیر.
		var search = content.querySelector( '.bp-search' );
		if ( search ) {
			var timer = null;
			search.addEventListener( 'input', function () {
				clearTimeout( timer );
				timer = setTimeout( function () {
					crudState.filters.s = search.value;
					state.page = 1;
					renderCrud( content, crudState.view );
				}, 300 );
			} );
		}

		// فیلترها.
		content.querySelectorAll( '.bp-filter' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				crudState.filters[ sel.dataset.filter ] = sel.value;
				state.page = 1;
				renderCrud( content, crudState.view );
			} );
		} );

		// صفحه‌بندی.
		content.querySelectorAll( '.bp-page' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				state.page = parseInt( b.dataset.page, 10 ) || 1;
				renderCrud( content, crudState.view );
			} );
		} );

		// افزودن.
		var addBtn = content.querySelector( '#bp-add-btn' );
		if ( addBtn && res && res.form ) {
			addBtn.addEventListener( 'click', function () {
				openCrudModal( content, res.form, 0, {} );
			} );
		}

		// پیش‌نمایش قیمت.
		var pvBtn = content.querySelector( '#bp-preview-btn' );
		if ( pvBtn ) {
			pvBtn.addEventListener( 'click', function () {
				openPreviewModal( content );
			} );
		}

		// عملیات ردیف.
		content.querySelectorAll( '.bp-actions .bp-btn' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = parseInt( b.dataset.id, 10 ) || 0;
				var action = b.dataset.action;
				var label = b.textContent;

				if ( 'delete' === action ) {
					if ( ! window.confirm( 'آیا از حذف این مورد مطمئن هستید؟' ) ) { return; }
				}
				if ( 'print' === action ) {
					openInvoicePrint( id );
					return;
				}
				if ( 'token' === action ) {
					issueUserToken( content, id );
					return;
				}

				b.disabled = true;
				b.textContent = '...';

				api( 'erp/' + crudState.view + '/' + id + '/' + action, { method: 'POST' } )
					.then( function ( r ) {
						if ( r && r.api_secret ) {
							showSecret( content, r.api_secret );
						}
						crudAlert( content, 'عملیات انجام شد.', false );
						renderCrud( content, crudState.view );
					} )
					.catch( function ( err ) {
						b.disabled = false;
						b.textContent = label;
						crudAlert( content, err.message, true );
					} );
			} );
		} );
	}

	/* ---------- مودال CRUD ---------- */

	function openModal( title, bodyHtml ) {
		closeModal();
		var wrap = document.createElement( 'div' );
		wrap.className = 'bp-modal';
		wrap.id = 'bp-modal';
		wrap.innerHTML =
			'<div class="bp-modal__backdrop"></div>' +
			'<div class="bp-modal__dialog">' +
				'<div class="bp-modal__head"><h3>' + esc( title ) + '</h3>' +
				'<button class="bp-modal__close" id="bp-modal-close">×</button></div>' +
				'<div class="bp-modal__body">' + bodyHtml + '</div>' +
			'</div>';
		document.body.appendChild( wrap );
		wrap.querySelector( '.bp-modal__backdrop' ).addEventListener( 'click', closeModal );
		wrap.querySelector( '#bp-modal-close' ).addEventListener( 'click', closeModal );
		return wrap;
	}

	function closeModal() {
		var m = document.getElementById( 'bp-modal' );
		if ( m ) { m.remove(); }
	}

	function fieldValue( field, value ) {
		var v = ( value === undefined || value === null ) ? '' : value;
		var name = 'field-' + field.name;
		var req = field.required ? ' required' : '';
		var html = '';

		switch ( field.type ) {
			case 'select':
				html = '<select class="bp-input" id="' + name + '"' + req + '>';
				( field.options || [] ).forEach( function ( o ) {
					var val = String( o.value !== undefined ? o.value : o );
					var lbl = o.label !== undefined ? o.label : o;
					html += '<option value="' + esc( val ) + '">' + esc( lbl ) + '</option>';
				} );
				html += '</select>';
				break;
			case 'textarea':
				html = '<textarea class="bp-input" id="' + name + '" rows="3"' + req + '>' + esc( v ) + '</textarea>';
				break;
			case 'checkbox':
				html = '<input type="checkbox" class="bp-input" id="' + name + '"' + ( v ? ' checked' : '' ) + '>';
				break;
			case 'number':
				html = '<input type="number" step="any" class="bp-input" id="' + name + '" value="' + esc( v ) + '"' + req + '>';
				break;
			case 'password':
				html = '<input type="password" class="bp-input" id="' + name + '" value="' + esc( v ) + '"' + req + '>';
				break;
			case 'date':
				html = '<input type="date" class="bp-input" id="' + name + '" value="' + esc( v ) + '"' + req + '>';
				break;
			default:
				html = '<input type="text" class="bp-input" id="' + name + '" value="' + esc( v ) + '"' + req + '>';
		}

		return '<div class="bp-field"><label for="' + name + '">' + esc( field.label ) + '</label>' + html + '</div>';
	}

	function readFieldValue( field ) {
		var el = document.getElementById( 'field-' + field.name );
		if ( ! el ) { return undefined; }
		if ( 'checkbox' === field.type ) { return el.checked; }
		if ( 'number' === field.type ) { return el.value === '' ? '' : parseFloat( el.value ); }
		return el.value;
	}

	function openCrudModal( content, form, id, values ) {
		var fieldsHtml = '';
		( form.fields || [] ).forEach( function ( f ) {
			fieldsHtml += fieldValue( f, values[ f.name ] );
		} );

		var body = '<div id="bp-modal-alert"></div>' + fieldsHtml +
			'<div class="bp-modal__foot"><button class="bp-btn bp-btn--primary" id="bp-modal-save">ذخیره</button></div>';

		var modal = openModal( id ? 'ویرایش' : 'افزودن', body );

		modal.querySelector( '#bp-modal-save' ).addEventListener( 'click', function () {
			var btn = modal.querySelector( '#bp-modal-save' );
			var data = {};
			( form.fields || [] ).forEach( function ( f ) {
				data[ f.name ] = readFieldValue( f );
			} );

			btn.disabled = true;
			btn.textContent = 'در حال ذخیره…';

			var path = 'erp/' + crudState.view;
			if ( id ) { path += '/' + id; }

			api( path, { method: 'POST', body: data } )
				.then( function ( r ) {
					if ( r && r.api_secret ) {
						showSecret( content, r.api_secret );
					}
					closeModal();
					crudAlert( content, 'ذخیره شد.', false );
					renderCrud( content, crudState.view );
				} )
				.catch( function ( err ) {
					btn.disabled = false;
					btn.textContent = 'ذخیره';
					var box = modal.querySelector( '#bp-modal-alert' );
					if ( box ) {
						box.innerHTML = '<div class="bp-alert bp-alert--error">' + esc( err.message ) + '</div>';
					}
				} );
		} );
	}

	/* ---------- نمایش یک‌باره‌ی راز/توکن ---------- */

	function showSecret( content, secret ) {
		closeModal();
		var body =
			'<div class="bp-alert bp-alert--warn">این کلید فقط یک‌بار نمایش داده می‌شود. آن را در جای امن ذخیره کنید.</div>' +
			'<div class="bp-token"><code id="bp-token-value">' + esc( secret ) + '</code>' +
			'<button class="bp-btn bp-btn--ghost" id="bp-copy-token">کپی</button></div>';
		var modal = openModal( 'کلید جدید', body );

		var copyBtn = modal.querySelector( '#bp-copy-token' );
		copyBtn.addEventListener( 'click', function () {
			var code = modal.querySelector( '#bp-token-value' );
			if ( code ) {
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( code.textContent );
				} else {
					var range = document.createRange();
					range.selectNode( code );
					window.getSelection().removeAllRanges();
					window.getSelection().addRange( range );
					document.execCommand( 'copy' );
				}
				copyBtn.textContent = 'کپی شد!';
			}
		} );
	}

	function issueUserToken( content, id ) {
		api( 'erp/users/' + id + '/token', { method: 'POST' } )
			.then( function ( r ) {
				if ( r && r.token ) {
					showSecret( content, r.token );
				}
			} )
			.catch( function ( err ) {
				crudAlert( content, err.message, true );
			} );
	}

	/* ---------- پیش‌نمایش قیمت ---------- */

	function openPreviewModal( content ) {
		var body =
			'<div id="bp-modal-alert"></div>' +
			'<div class="bp-field"><label for="field-pv-product">محصول (شناسه)</label>' +
			'<input type="number" class="bp-input" id="field-pv-product" required></div>' +
			'<div class="bp-field"><label for="field-pv-channel">کانال (شناسه)</label>' +
			'<input type="number" class="bp-input" id="field-pv-channel" required></div>' +
			'<div class="bp-field"><label for="field-pv-type">نوع فروش</label>' +
			'<select class="bp-input" id="field-pv-type"><option value="cash">نقدی</option><option value="credit">اعتباری</option></select></div>' +
			'<div class="bp-field"><label for="field-pv-qty">تعداد</label>' +
			'<input type="number" class="bp-input" id="field-pv-qty" value="1" min="1"></div>' +
			'<div class="bp-modal__foot"><button class="bp-btn bp-btn--primary" id="bp-pv-run">محاسبه</button></div>' +
			'<div id="bp-pv-result"></div>';

		var modal = openModal( 'پیش‌نمایش قیمت', body );

		modal.querySelector( '#bp-pv-run' ).addEventListener( 'click', function () {
			var data = {
				product_id: parseInt( modal.querySelector( '#field-pv-product' ).value, 10 ) || 0,
				channel_id: parseInt( modal.querySelector( '#field-pv-channel' ).value, 10 ) || 0,
				sale_type: modal.querySelector( '#field-pv-type' ).value,
				quantity: parseInt( modal.querySelector( '#field-pv-qty' ).value, 10 ) || 1
			};

			if ( ! data.product_id || ! data.channel_id ) {
				modal.querySelector( '#bp-modal-alert' ).innerHTML =
					'<div class="bp-alert bp-alert--error">محصول و کانال الزامی است.</div>';
				return;
			}

			api( 'erp/pricing/preview', { method: 'POST', body: data } )
				.then( function ( r ) {
					var box = modal.querySelector( '#bp-pv-result' );
					var out = '<div class="bp-card" style="margin-top:12px"><table class="bp-table"><tbody>';
					Object.keys( r.preview || {} ).forEach( function ( k ) {
						out += '<tr><th>' + esc( k ) + '</th><td>' + esc( r.preview[ k ] ) + '</td></tr>';
					} );
					out += '</tbody></table></div>';
					box.innerHTML = out;
				} )
				.catch( function ( err ) {
					modal.querySelector( '#bp-modal-alert' ).innerHTML =
						'<div class="bp-alert bp-alert--error">' + esc( err.message ) + '</div>';
				} );
		} );
	}

	/* ---------- چاپ صورتحساب ---------- */

	function openInvoicePrint( id ) {
		api( 'erp/invoices/' + id + '/print' )
			.then( function ( r ) {
				if ( r && r.ok && r.html ) {
					var w = window.open( '', '_blank' );
					if ( w ) {
						w.document.write( r.html );
						w.document.close();
						w.focus();
						setTimeout( function () { w.print(); }, 350 );
					}
				}
			} )
			.catch( function ( err ) {
				var content = document.getElementById( 'bp-content' );
				if ( content ) {
					crudAlert( content, err.message, true );
				}
			} );
	}

	/* ---------- فرم تنظیمات ---------- */

	function renderSettingsForm( content, res ) {
		var s = res.settings || {};
		var sys = res.system || {};

		var html = '<div class="bp-card" style="padding:20px">';
		html += '<div class="bp-field"><label for="field-currency">واحد پول</label>' +
			'<select class="bp-input" id="field-currency">' +
			'<option value="IRT"' + ( 'IRT' === s.currency ? ' selected' : '' ) + '>تومان (IRT)</option>' +
			'<option value="IRR"' + ( 'IRR' === s.currency ? ' selected' : '' ) + '>ریال (IRR)</option></select></div>';
		html += '<div class="bp-field"><label for="field-tax">نرخ مالیات (٪)</label>' +
			'<input type="number" step="any" class="bp-input" id="field-tax" value="' + esc( String( s.tax_rate || 0 ) ) + '"></div>';
		html += '<div class="bp-field"><label for="field-audit">لاگ تغییرات فعال</label>' +
			'<input type="checkbox" class="bp-input" id="field-audit"' + ( s.audit_log_active ? ' checked' : '' ) + '></div>';
		html += '<div class="bp-modal__foot"><button class="bp-btn bp-btn--primary" id="bp-save-settings">ذخیره تنظیمات</button></div>';
		html += '</div>';

		html += '<div class="bp-card" style="padding:20px;margin-top:16px"><h2 class="bp-section">اطلاعات سیستم</h2>' +
			'<table class="bp-table"><tbody>' +
			'<tr><th>نسخه افزونه</th><td>' + esc( sys.version || '—' ) + '</td></tr>' +
			'<tr><th>نسخه دیتابیس</th><td>' + esc( sys.db_version || '—' ) + '</td></tr>' +
			'<tr><th>قیمت‌گذاری</th><td>' + esc( sys.pricing_version || '—' ) + '</td></tr>' +
			'<tr><th>ووکامرس</th><td>' + ( sys.woocommerce ? 'فعال' : 'غیرفعال' ) + '</td></tr>' +
			'</tbody></table></div>';

		content.innerHTML = html;

		content.querySelector( '#bp-save-settings' ).addEventListener( 'click', function () {
			var btn = content.querySelector( '#bp-save-settings' );
			var data = {
				currency: content.querySelector( '#field-currency' ).value,
				tax_rate: parseFloat( content.querySelector( '#field-tax' ).value ) || 0,
				audit_log_active: content.querySelector( '#field-audit' ).checked
			};

			btn.disabled = true;
			btn.textContent = 'در حال ذخیره…';

			api( 'erp/settings', { method: 'POST', body: data } )
				.then( function () {
					btn.disabled = false;
					btn.textContent = 'ذخیره تنظیمات';
					crudAlert( content, 'تنظیمات ذخیره شد.', false );
				} )
				.catch( function ( err ) {
					btn.disabled = false;
					btn.textContent = 'ذخیره تنظیمات';
					crudAlert( content, err.message, true );
				} );
		} );
	}

	boot();
} )();
