$(function () {

	if (!document.getElementById) {
		return;
	}

	if (document.getElementById('map_canvas')) {
		// Misc functions

		function trim(myString) {
			return myString.replace(/^\s+/g, '').replace(/\s+$/g, '')
		}

		$('#settings').on('onetabload', function () {
			resizeMap();
		});

		$('#gmap-area label').on('click', function () {
			resizeMap();
		});

		function resizeMap() {

			if ($('input[name="myGmaps_center"]').attr('value') == '') {
				var default_location = new google.maps.LatLng(43.0395797336425, 6.126280043989323);
				var default_zoom = '12';
				var default_type = 'roadmap';
			} else {
				var parts = $('input[name="myGmaps_center"]').attr('value').split(",");
				var lat = parseFloat(trim(parts[0]));
				var lng = parseFloat(trim(parts[1]));
				var default_location = new google.maps.LatLng(lat, lng);
				var default_zoom = $('input[name="myGmaps_zoom"]').attr('value');
				var default_type = $('input[name="myGmaps_type"]').attr('value');
			}
			google.maps.event.trigger(map_add, 'resize');
			map_add.setCenter(default_location);
			map_add.setZoom(parseFloat(default_zoom));
		}

		function updateMapOptions() {

			var default_location = map_add.getCenter().lat() + ',' + map_add.getCenter().lng();
			var default_zoom = map_add.getZoom();
			var default_type = map_add.getMapTypeId();

			$('input[name=myGmaps_center]').attr('value', default_location);
			$('input[name=myGmaps_zoom]').attr('value', default_zoom);
			$('input[name=myGmaps_type]').attr('value', default_type);
		}

		// Display map with default or saved values

		if ($('input[name="myGmaps_center"]').attr('value') == '') {
			var default_location = new google.maps.LatLng(43.0395797336425, 6.126280043989323);
			var default_zoom = '12';
			var default_type = 'roadmap';

			$('input[name="myGmaps_center"]').attr('value', default_location);
			$('input[name="myGmaps_zoom"]').attr('value', default_zoom);
			$('input[name="myGmaps_type"]').attr('value', default_type);
		} else {
			var parts = $('input[name="myGmaps_center"]').attr('value').split(",");
			var lat = parseFloat(trim(parts[0]));
			var lng = parseFloat(trim(parts[1]));
			var default_location = new google.maps.LatLng(lat, lng);
			var default_zoom = $('input[name="myGmaps_zoom"]').attr('value');
			var default_type = $('input[name="myGmaps_type"]').attr('value');
		}

		// Map styles. Get more styles from http://snazzymaps.com/

		var mapTypeIds = [google.maps.MapTypeId.ROADMAP,
			google.maps.MapTypeId.HYBRID,
			google.maps.MapTypeId.SATELLITE,
			google.maps.MapTypeId.TERRAIN,
			'OpenStreetMap',
			'neutral_blue'
		];


		var map_styles_list = $('#map_styles_list').attr('value');
		var styles_array = map_styles_list.split(',');
		for (i in styles_array) {
			value = styles_array[i].replace("_styles.js", "");
			mapTypeIds.push(value);

		}
		var myOptions = {
			zoom: parseFloat(default_zoom),
			center: default_location,
			scrollwheel: false,
			mapTypeControl: true,
			mapTypeControlOptions: {
				mapTypeIds: mapTypeIds
			}
		};

		map_add = new google.maps.Map(document.getElementById("map_canvas"), myOptions);

		// Credit OSM if we can ;)

		var credit = '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap Contributors</a>';

		var creditNode = document.createElement('div');
		creditNode.id = 'credit-control';
		creditNode.style.fontSize = '10px';
		creditNode.style.fontFamily = 'Arial, sans-serif';
		creditNode.style.margin = '0';
		creditNode.style.whitespace = 'nowrap';
		creditNode.index = 0;

		if (default_type == 'roadmap') {
			map_add.setOptions({
				mapTypeId: google.maps.MapTypeId.ROADMAP
			});
		} else if (default_type == 'satellite') {
			map_add.setOptions({
				mapTypeId: google.maps.MapTypeId.SATELLITE
			});
		} else if (default_type == 'hybrid') {
			map_add.setOptions({
				mapTypeId: google.maps.MapTypeId.HYBRID
			});
		} else if (default_type == 'terrain') {
			map_add.setOptions({
				mapTypeId: google.maps.MapTypeId.TERRAIN
			});
		} else if (default_type == 'OpenStreetMap') {
			map_add.setOptions({
				mapTypeId: 'OpenStreetMap'
			});
			map_add.controls[google.maps.ControlPosition.BOTTOM_RIGHT].push(creditNode);
			creditNode.innerHTML = credit;
		} else {
			map_add.setOptions({
				mapTypeId: default_type
			});
		}

		map_add.mapTypes.set('neutral_blue', neutral_blue);

		map_add.mapTypes.set('OpenStreetMap', new google.maps.ImageMapType({
			getTileUrl: function (coord, zoom) {
				return "https://tile.openstreetmap.org/" + zoom + "/" + coord.x + "/" + coord.y + ".png";
			},
			tileSize: new google.maps.Size(256, 256),
			name: "OpenStreetMap",
			maxZoom: 18
		}));

		for (i in mapTypeIds) {
			if (i < 6) {
				continue;
			}
			var value = window[mapTypeIds[i]];
			map_add.mapTypes.set(mapTypeIds[i], value);
		}

		geocoder = new google.maps.Geocoder();

		var input = document.getElementById('address');
		var autocomplete = new google.maps.places.Autocomplete(input);

		// Map listeners

		google.maps.event.addListener(map_add, 'center_changed', function () {
			window.setTimeout(function () {
				var center = map_add.getCenter();
			}, 100);
			updateMapOptions();
		});

		google.maps.event.addListener(map_add, 'zoom_changed', function () {
			updateMapOptions();
		});

		google.maps.event.addListener(map_add, 'maptypeid_changed', function () {
			if (map_add.getMapTypeId() == 'OpenStreetMap') {
				map_add.controls[google.maps.ControlPosition.BOTTOM_RIGHT].push(creditNode);
				creditNode.innerHTML = credit;
			} else {
				map_add.controls[google.maps.ControlPosition.BOTTOM_RIGHT].clear(creditNode);
			}
			updateMapOptions();
		});

		// Geocoding

		geocoder = new google.maps.Geocoder();

		function geocode() {
			var address = document.getElementById("address").value;
			geocoder.geocode({
				'address': address,
				'partialmatch': true
			}, geocodeResult);

		}

		function geocodeResult(results, status) {
			if (status == 'OK' && results.length > 0) {
				map_add.fitBounds(results[0].geometry.viewport);
			} else {
				alert("Geocode was not successful for the following reason: " + status);
			}
		}

		$('#geocode').on('click', function () {
			geocode();
			return false;
		});

		// Submit form and save

		$('#form-entries').submit(function () {

			$('.maplist :checkbox').each(function () {
				if ($(this).attr("checked")) {
					$('<p style="display:none"><input type="checkbox" name="entries[]" value="' + $(this).val() + '" checked="checked" /></p>').insertAfter("#map_canvas");
				}
			});
			return true;

		});

	}
});