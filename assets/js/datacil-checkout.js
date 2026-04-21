/**
 * Checkout logic:
 * - Muestra/oculta el campo `billing_vat` segun country = EC.
 * - Boton "Validar": AJAX → Datacil → fuerza country=EC → espera refresh
 *   de states → matchea provincia por nombre → rellena state, city, etc.
 *
 * API key nunca expuesta al browser: todo pasa por admin-ajax.php (PHP).
 */
(function ($) {
	'use strict';

	var REQUIRED_COUNTRY = (DatacilWC && DatacilWC.countryRequired) || 'EC';

	function $wrapper($field) {
		return $field.closest('.form-row, p.form-row, .woocommerce-input-wrapper').first();
	}

	function toggleVatByCountry() {
		var country = $('#billing_country').val() || '';
		var show = country === REQUIRED_COUNTRY;
		var $field = $('#billing_vat');
		if (!$field.length) return;

		var $row = $wrapper($field);
		if (!$row.length) $row = $field.parent();
		$row.toggleClass('datacil-hidden', !show);

		// Required dinamico: si EC, marcar required; si no, quitar.
		if (show) {
			$field.attr('required', 'required');
		} else {
			$field.removeAttr('required');
		}
	}

	function injectButton($field) {
		if ($field.data('datacilReady')) return;
		$field.data('datacilReady', true);

		var $btn = $('<button type="button" class="button datacil-validate-btn"></button>').text(DatacilWC.i18n.validate);
		var $msg = $('<span class="datacil-validate-msg"></span>');
		$field.after($msg);
		$field.after($btn);

		$btn.on('click', function (e) {
			e.preventDefault();
			var vat = String($field.val() || '').replace(/\D/g, '');
			if (vat.length !== 10 && vat.length !== 13) {
				$msg.text(DatacilWC.i18n.invalid_length).removeClass('ok').addClass('error');
				return;
			}
			$btn.prop('disabled', true).text(DatacilWC.i18n.validating);
			$msg.text('').removeClass('ok error');

			$.post(DatacilWC.ajaxUrl, {
				action: 'datacil_validate',
				nonce: DatacilWC.nonce,
				vat: vat
			}).done(function (res) {
				$btn.prop('disabled', false).text(DatacilWC.i18n.validate);
				if (!res || !res.success) {
					$msg.text((res && res.message) || DatacilWC.i18n.failure).removeClass('ok').addClass('error');
					return;
				}
				applyApiData(res.data || {});
				$msg.text(DatacilWC.i18n.success + ': ' + ((res.data && res.data.name) || '')).removeClass('error').addClass('ok');
			}).fail(function () {
				$btn.prop('disabled', false).text(DatacilWC.i18n.validate);
				$msg.text(DatacilWC.i18n.failure).removeClass('ok').addClass('error');
			});
		});
	}

	/**
	 * Aplica data del API al form billing. Country=EC primero; WC refresca
	 * states via AJAX; en el evento `country_to_state_changed` hacemos
	 * match del state por nombre y seteamos city.
	 */
	function applyApiData(d) {
		// Nombre/apellido desde "name" (primera palabra = first, resto = last)
		if (d.name) {
			var parts = String(d.name).trim().split(/\s+/);
			var first = parts.shift();
			var last = parts.join(' ');
			fill('#billing_first_name', first);
			fill('#billing_last_name', last);
		}

		fill('#billing_address_1', d.street);
		fill('#billing_email', d.email);
		fill('#billing_phone', d.phone || d.cellphone);

		var $country = $('#billing_country');
		var stateName = d.state || '';
		var cityName = d.city || '';

		if ($country.length) {
			// Si ya es EC, saltamos el change y seteamos state/city directo.
			if ($country.val() === REQUIRED_COUNTRY) {
				setStateByName(stateName);
				fill('#billing_city', cityName);
			} else {
				// Esperamos a que WC reemplace el select de state al cambiar pais.
				$(document.body).one('country_to_state_changed', function () {
					setStateByName(stateName);
					fill('#billing_city', cityName);
				});
				$country.val(REQUIRED_COUNTRY).trigger('change');
				// Fallback si el evento no dispara (fragments congelados).
				setTimeout(function () {
					setStateByName(stateName);
					fill('#billing_city', cityName);
				}, 800);
			}
		} else {
			fill('#billing_city', cityName);
		}

		toggleVatByCountry();
	}

	/**
	 * Busca un <option> cuyo texto coincida (case-insensitive, trim) con el
	 * nombre de la provincia y setea el value. WC usa codigos ISO 3166-2
	 * para EC (ej. Pichincha = "P"). Tambien maneja input text fallback.
	 */
	function setStateByName(stateName) {
		if (!stateName) return;
		var $state = $('#billing_state');
		if (!$state.length) return;

		if ($state.is('select')) {
			var target = String(stateName).trim().toLowerCase();
			var found = null;
			$state.find('option').each(function () {
				var txt = String($(this).text()).trim().toLowerCase();
				if (txt === target || txt.indexOf(target) !== -1 || target.indexOf(txt) !== -1) {
					found = $(this).val();
					return false;
				}
			});
			if (found) $state.val(found).trigger('change');
		} else {
			$state.val(stateName).trigger('change');
		}
	}

	function fill(selector, value) {
		if (value === null || value === undefined || value === '') return;
		var $el = $(selector);
		if (!$el.length) return;
		if (!$el.val()) $el.val(value).trigger('change');
	}

	$(function () {
		var $doc = $(document.body);

		// Inicial + cada vez que WC refresca fragments del checkout.
		$doc.on('updated_checkout init_checkout', function () {
			injectButton($('#billing_vat'));
			toggleVatByCountry();
		});

		// Cambio directo de pais (sin re-render de fragments).
		$doc.on('change', '#billing_country', toggleVatByCountry);

		// Mi Cuenta → Editar direccion (no dispara updated_checkout).
		injectButton($('#billing_vat'));
		toggleVatByCountry();
	});
})(jQuery);
