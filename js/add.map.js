'use strict';

dotclear.ready(() => {

	if (!document.getElementById) {
		return;
	}

	if (!document.getElementById('map_canvas')) {
		return;
	}

	let map_add;

	async function initMap() {
		// Request libraries when needed, not in the script tag.
		const { Map } = await google.maps.importLibrary("maps");
		const { Places } = await google.maps.importLibrary("places");
		const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

		// Display map with default or saved values
		const centerInput = document.querySelector('input[name="myGmaps_center"]');
		const zoomInput = document.querySelector('input[name="myGmaps_zoom"]');
		const typeInput = document.querySelector('input[name="myGmaps_type"]');

		let default_zoom;
		let default_type;
		let default_location;

		if (centerInput.value === '') {
			default_location = new google.maps.LatLng(43.0395797336425, 6.126280043989323);
			default_zoom = '12';
			default_type = 'roadmap';

			centerInput.value = default_location;
			zoomInput.value = default_zoom;
			typeInput.value = default_type;
		} else {
			const parts = centerInput.value.split(",");
			const lat = parseFloat(trim(parts[0]));
			const lng = parseFloat(trim(parts[1]));
			default_location = new google.maps.LatLng(lat, lng);
			default_zoom = zoomInput.value;
			default_type = typeInput.value;
		}

		// Map styles. Get more styles from https://snazzymaps.com/

		const neutral_blue_styles = [{ "featureType": "water", "elementType": "geometry", "stylers": [{ "color": "#193341" }] }, { "featureType": "landscape", "elementType": "geometry", "stylers": [{ "color": "#2c5a71" }] }, { "featureType": "road", "elementType": "geometry", "stylers": [{ "color": "#29768a" }, { "lightness": -37 }] }, { "featureType": "poi", "elementType": "geometry", "stylers": [{ "color": "#406d80" }] }, { "featureType": "transit", "elementType": "geometry", "stylers": [{ "color": "#406d80" }] }, { "elementType": "labels.text.stroke", "stylers": [{ "visibility": "on" }, { "color": "#3e606f" }, { "weight": 2 }, { "gamma": 0.84 }] }, { "elementType": "labels.text.fill", "stylers": [{ "color": "#ffffff" }] }, { "featureType": "administrative", "elementType": "geometry", "stylers": [{ "weight": 0.6 }, { "color": "#1a3541" }] }, { "elementType": "labels.icon", "stylers": [{ "visibility": "off" }] }, { "featureType": "poi.park", "elementType": "geometry", "stylers": [{ "color": "#2c5a71" }] }];
		const neutral_blue = new google.maps.StyledMapType(neutral_blue_styles, { name: "Neutral Blue" });

		const mapTypeIds = [google.maps.MapTypeId.ROADMAP,
		google.maps.MapTypeId.HYBRID,
		google.maps.MapTypeId.SATELLITE,
		google.maps.MapTypeId.TERRAIN,
			'OpenStreetMap',
			'neutral_blue'
		];

		const map_styles_list = document.getElementById('map_styles_list').value;
		const styles_array = map_styles_list.split(',');
		for (const i in styles_array) {
			const value = styles_array[i].replace("_styles.js", "");
			mapTypeIds.push(value);

			const user_style = dotclear.getData(value);

			window[value] = new google.maps.StyledMapType(user_style.style, { name: user_style.name });
		}
		const myOptions = {
			mapId: "add_map",
			zoom: parseFloat(default_zoom),
			center: default_location,
			scrollwheel: false,
			mapTypeControl: true,
			streetViewControl: false,
			mapTypeControlOptions: {
				mapTypeIds
			}
		};

		map_add = new Map(document.getElementById("map_canvas"), myOptions);

		// Credit OSM if we can ;)
		const credit = '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap Contributors</a>';

		const creditNode = document.createElement('div');
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
			getTileUrl(coord, zoom) {
				return `https://tile.openstreetmap.org/${zoom}/${coord.x}/${coord.y}.png`;
			},
			tileSize: new google.maps.Size(256, 256),
			name: "OpenStreetMap",
			maxZoom: 18
		}));

		for (const i in mapTypeIds) {
			if (i < 6) {
				continue;
			}
			const value = window[mapTypeIds[i]];
			map_add.mapTypes.set(mapTypeIds[i], value);
		}

		// Autocomplete
		const geocoder = new google.maps.Geocoder();
		const address = document.getElementById('address');
		const geocodeok = document.getElementById('geocode');
		const toolbar = document.getElementById('map_toolbar');

		const placeAutocomplete = new google.maps.places.PlaceAutocompleteElement();
		toolbar.insertBefore(placeAutocomplete, geocodeok);

		placeAutocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
			const place = placePrediction.toPlace();
			await place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });
			address.value = place.formattedAddress;
		});

		// Geocode
		function geocode() {
			const address = document.getElementById("address").value;
			geocoder.geocode({ 'address': address, 'partialmatch': true }, geocodeResult);
		}

		function geocodeResult(results, status) {
			if (status === 'OK' && results.length > 0) {
				map.fitBounds(results[0].geometry.viewport);
			} else {
				alert(`Geocode was not successful for the following reason: ${status}`);
			}
		}

		document.getElementById('geocode').addEventListener('click', (e) => {
			e.preventDefault();
			geocode();
		});

		// Map listeners
		google.maps.event.addListener(map_add, 'center_changed', () => {
			window.setTimeout(() => {
				const center = map_add.getCenter();
			}, 100);
			updateMapOptions();
		});

		google.maps.event.addListener(map_add, 'zoom_changed', () => {
			updateMapOptions();
		});

		google.maps.event.addListener(map_add, 'maptypeid_changed', () => {
			if (map_add.getMapTypeId() == 'OpenStreetMap') {
				map_add.controls[google.maps.ControlPosition.BOTTOM_RIGHT].push(creditNode);
				creditNode.innerHTML = credit;
			} else {
				map_add.controls[google.maps.ControlPosition.BOTTOM_RIGHT].clear(creditNode);
			}
			updateMapOptions();
		});

		if (typeof initElements === 'function') {
			initElements(map_add);
		}

		// Misc functions
		function trim(myString) {
			return myString.replace(/^\s+/g, '').replace(/\s+$/g, '');
		}

		function updateMapOptions() {
			const default_location = `${map_add.getCenter().lat()},${map_add.getCenter().lng()}`;
			const default_zoom = map_add.getZoom();
			const default_type = map_add.getMapTypeId();

			centerInput.value = default_location;
			zoomInput.value = default_zoom;
			typeInput.value = default_type;
		}

	}

	initMap();

});