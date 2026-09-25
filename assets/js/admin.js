/**
 * Bespari ERP — Admin JS
 * تب‌ها، تایید حذف، و پیش‌نمایش قیمت (AJAX).
 */
(function ($) {
	'use strict';

	$(function () {

		// تایید حذف (data-message).
		$(document).on('click', '.bespari-confirm-delete', function (e) {
			var msg = $(this).data('message') || (window.BespariAdmin && BespariAdmin.i18n && BespariAdmin.i18n.confirmDelete) || '';
			if (msg && !confirm(msg)) {
				e.preventDefault();
				return false;
			}
		});

		// پیش‌نمایش قیمت.
		var $btn = $('#bespari-preview-run');
		var $result = $('#bespari-preview-result');

		if ($btn.length && window.BespariAdmin) {
			$btn.on('click', function () {
				var productId = parseInt($('#bespari-preview-product').val() || '0', 10);
				var channelId = parseInt($('#bespari-preview-channel').val() || '0', 10);
				var saleType  = $('#bespari-preview-saletype').val() || 'cash';
				var qty       = parseInt($('#bespari-preview-qty').val() || '1', 10) || 1;

				if (!productId || !channelId) {
					$result.html('<p style="color:#991b1b;">' + (BespariAdmin.i18n.error || 'Error') + ' — محصول و کانال انتخاب کنید.</p>');
					return;
				}

				$btn.prop('disabled', true).text('…');
				$result.html('<p>در حال محاسبه…</p>');

				$.post(BespariAdmin.ajaxUrl, {
					action: 'bespari_preview_price',
					nonce: BespariAdmin.nonce,
					product_id: productId,
					channel_id: channelId,
					sale_type: saleType,
					quantity: qty
				})
				.done(function (res) {
					if (!res || !res.success || !res.data) {
						var msg = (res && res.data && res.data.message) ? res.data.message : (BespariAdmin.i18n.error || 'Error');
						$result.html('<p style="color:#991b1b;">' + msg + '</p>');
						return;
					}
					renderPreview(res.data, $result);
				})
				.fail(function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : (BespariAdmin.i18n.error || 'Error');
					$result.html('<p style="color:#991b1b;">' + msg + '</p>');
				})
				.always(function () {
					$btn.prop('disabled', false).text('محاسبه قیمت');
				});
			});
		}

		function escapeHtml(s) {
			if (s === null || s === undefined) return '';
			return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
		}

		function fmtMoney(n) {
			if (n === null || n === undefined) return '—';
			try { return Number(n).toLocaleString('fa-IR') + ' تومان'; } catch(e) { return String(n); }
		}

		function renderPreview(d, $el) {
			var f = d.financials || {};
			var html = '<table><tbody>';
			html += '<tr><th>قیمت پایه</th><td>' + fmtMoney(d.base_price) + '</td></tr>';
			html += '<tr><th>افزایش کانال</th><td>' + fmtMoney(d.channel_markup) + '</td></tr>';
			html += '<tr><th>مجموع قوانین</th><td>' + fmtMoney(d.rules_total) + '</td></tr>';
			if (d.applied_rules && d.applied_rules.length) {
				html += '<tr><th>قوانین اعمال‌شده</th><td>';
				d.applied_rules.forEach(function(r){ html += '<span class="bespari-badge bespari-badge--info">' + escapeHtml(r.title || r.rule_id) + ': ' + fmtMoney(r.amount) + '</span> '; });
				html += '</td></tr>';
			}
			html += '<tr><th>افزایش اعتباری</th><td>' + fmtMoney(d.credit_markup) + '</td></tr>';
			html += '<tr><th><strong>قیمت فروش واحد</strong></th><td><strong>' + fmtMoney(d.sale_price_unit) + '</strong></td></tr>';
			html += '<tr><th>قیمت کل (×' + (d.quantity||1) + ')</th><td>' + fmtMoney(d.sale_price_total) + '</td></tr>';
			html += '<tr><th colspan="2" style="background:#eff6ff;">کسورات</th></tr>';
			html += '<tr><th>مالیات</th><td>' + fmtMoney(f.tax) + '</td></tr>';
			html += '<tr><th>پورسانت</th><td>' + fmtMoney(f.commission) + '</td></tr>';
			html += '<tr><th>پردازش</th><td>' + fmtMoney(f.processing_fee) + '</td></tr>';
			html += '<tr><th>حمل</th><td>' + fmtMoney(f.shipping_fee) + '</td></tr>';
			html += '<tr><th>تبلیغات</th><td>' + fmtMoney(f.advertising_fee) + '</td></tr>';
			html += '<tr><th>درگاه</th><td>' + fmtMoney(f.gateway_fee) + '</td></tr>';
			html += '<tr><th>ثابت</th><td>' + fmtMoney(f.fixed_fee) + '</td></tr>';
			html += '<tr><th>جمع کسورات</th><td>' + fmtMoney(f.total_deductions) + '</td></tr>';
			html += '<tr><th>خالص قابل دریافت</th><td>' + fmtMoney(f.net_receivable) + '</td></tr>';
			html += '<tr><th>هزینه محصول</th><td>' + fmtMoney(f.product_cost) + '</td></tr>';
			html += '<tr><th><strong>سود</strong></th><td><strong>' + fmtMoney(f.profit) + '</strong></td></tr>';
			html += '<tr><th>نسخه قوانین</th><td><code>' + escapeHtml(d.rule_version) + '</code></td></tr>';
			html += '</tbody></table>';
			$el.html(html);
		}

		// فرم سفارش: افزودن/حذف ردیف و پیش‌نمایش.
		(function(){
			var $body = $('#bespari-order-items-body');
			if (!$body.length) return;
			var idx = $body.find('.bespari-order-item-row').length;
			var tpl = document.getElementById('bespari-item-row-template');

			$(document).on('click', '#bespari-add-item', function(){
				if (!tpl || !tpl.innerHTML) return;
				var html = tpl.innerHTML.replace(/__IDX__/g, String(idx++));
				$body.append(html);
			});
			$(document).on('click', '.bespari-remove-item', function(){
				var $rows = $body.find('.bespari-order-item-row');
				if ($rows.length <= 1) return;
				$(this).closest('.bespari-order-item-row').remove();
			});
			$(document).on('click', '#bespari-preview-order', function(){
				var channelId = parseInt($('#bespari-order-channel').val()||'0',10);
				var saleType  = $('#bespari-order-sale-type').val()||'cash';
				var items = [];
				$body.find('.bespari-order-item-row').each(function(){
					var pid = parseInt($(this).find('select').val()||'0',10);
					var qty = parseInt($(this).find('input[type=number]').val()||'1',10)||1;
					if (pid) items.push({product_id: pid, quantity: qty});
				});
				var $out = $('#bespari-order-preview-result');
				if (!channelId || !items.length) {
					$out.html('<p style="color:#991b1b;">کانال و حداقل یک محصول انتخاب کنید.</p>');
					return;
				}
				var $btn = $(this);
				$btn.prop('disabled', true).text('…');
				$out.html('<p>در حال محاسبه…</p>');
				$.post(BespariAdmin.ajaxUrl, {
					action: 'bespari_order_preview',
					nonce: BespariAdmin.nonce,
					channel_id: channelId,
					sale_type: saleType,
					items: items
				}).done(function(res){
					if (!res || !res.success || !res.data) {
						var msg = (res && res.data && res.data.message) ? res.data.message : (BespariAdmin.i18n.error||'Error');
						$out.html('<p style="color:#991b1b;">'+escapeHtml(msg)+'</p>');
						return;
					}
					var d = res.data;
					var html = '<table><tbody>';
					d.items_breakdown && d.items_breakdown.forEach(function(it, i){
						html += '<tr><th colspan="2" style="background:#f0f0ff;">ردیف '+(i+1)+' — '+escapeHtml(it.product_name||it.product_id)+'</th></tr>';
						html += '<tr><th>قیمت واحد</th><td>'+fmtMoney(it.unit_price)+'</td></tr>';
						html += '<tr><th>× تعداد ('+it.quantity+')</th><td>'+fmtMoney(it.line_total)+'</td></tr>';
					});
					html += '<tr><th><strong>جمع ناخالص</strong></th><td><strong>'+fmtMoney(d.gross)+'</strong></td></tr>';
					html += '<tr><th>کسورات</th><td>'+fmtMoney(d.deductions)+'</td></tr>';
					html += '<tr><th>خالص</th><td>'+fmtMoney(d.net)+'</td></tr>';
					html += '<tr><th>سود</th><td>'+fmtMoney(d.profit)+'</td></tr>';
					html += '<tr><th>نسخه قوانین</th><td><code>'+escapeHtml(d.rule_version)+'</code></td></tr>';
					html += '</tbody></table>';
					$out.html(html);
				}).fail(function(xhr){
					var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : (BespariAdmin.i18n.error||'Error');
					$out.html('<p style="color:#991b1b;">'+escapeHtml(msg)+'</p>');
				}).always(function(){ $btn.prop('disabled', false).text('پیش‌نمایش محاسبه'); });
			});
		})();

	});

})(jQuery);
