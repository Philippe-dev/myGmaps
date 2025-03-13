dotclear.ready(() => {

  if (!document.getElementById) {
    return;
  }

  if (!document.getElementById('map_canvas')) {
    return;
  }

  let map;

  async function initMap() {
    // Request libraries when needed, not in the script tag.
    const { Map } = await google.maps.importLibrary("maps");
    const { Places } = await google.maps.importLibrary("places");
    const { AdvancedMarkerElement, PinElement } = await google.maps.importLibrary("marker");

    // Misc functions

    function trim(myString) {
      return myString.replace(/^\s+/g, '').replace(/\s+$/g, '')
    }

    function is_url(str) {
      const exp = new RegExp("^(http(s)://)[a-zA-Z0-9.-]*[a-zA-Z0-9/_-]", "g");
      return exp.test(str);
    }

    // Toolbar actions

    document.querySelectorAll(".map_toolbar button").forEach(button => {
      button.addEventListener('click', function () {
        if (this.id === 'delete_map') {
          deleteMap();
        } else if (document.getElementById('post_excerpt').value === '') {
          document.querySelectorAll(".map_toolbar button").forEach(btn => {
            btn.classList.remove("active");
          });
          this.classList.add("active");
        }
      });
    });

    // OBJECTS UPDATE FUNCTIONS

    function updatePolyline() {
      vertexArray.length = 0;
      const len = polylinePath.getLength();
      for (let i = 0; i < len; i++) {
        vertexArray.push(`${polylinePath.getAt(i).lat()}|${polylinePath.getAt(i).lng()}`);
      }
      element_values = vertexArray.join('\n');
      element_values = `${element_values}\n${polyline.strokeWeight}` +
        "|" + polyline.strokeOpacity + "|" + polyline.strokeColor;
      document.getElementById('element_type').value = 'polyline';
      document.getElementById('post_excerpt').value = element_values;
    }

    function updatePolygon() {
      vertexArray.length = 0;
      const len = polygonPath.getLength();
      for (let i = 0; i < len; i++) {
        vertexArray.push(`${polygonPath.getAt(i).lat()}|${polygonPath.getAt(i).lng()}`);
      }
      element_values = vertexArray.join('\n');
      element_values = `${element_values}\n${polygon.strokeWeight}` +
        "|" + polygon.strokeOpacity + "|" + polygon.strokeColor +
        "|" + polygon.fillColor + "|" + polygon.fillOpacity;
      document.getElementById('element_type').value = 'polygon';
      document.getElementById('post_excerpt').value = element_values;
    }

    function updateRectangle() {
      const square = rectangle.getBounds();
      const NE = square.getNorthEast();
      const SW = square.getSouthWest();
      const ne = new google.maps.LatLng(NE.lat(), SW.lng());
      const sw = new google.maps.LatLng(SW.lat(), NE.lng());
      element_values = `${sw.lat()}|${ne.lng()}|${ne.lat()}|${sw.lng()}`;
      element_values = `${element_values}\n${rectangle.strokeWeight}` +
        "|" + rectangle.strokeOpacity + "|" + rectangle.strokeColor +
        "|" + rectangle.fillColor + "|" + rectangle.fillOpacity;
      document.getElementById('element_type').value = 'rectangle';
      document.getElementById('post_excerpt').value = element_values;
    }

    function updateCircle() {
      const center = circle.getCenter();
      const radius = circle.getRadius();

      element_values = `${center.lat()}|${center.lng()}|${radius}`;
      element_values = `${element_values}\n${circle.strokeWeight}` +
        "|" + circle.strokeOpacity + "|" + circle.strokeColor +
        "|" + circle.fillColor + "|" + circle.fillOpacity;
      document.getElementById('element_type').value = 'circle';
      document.getElementById('post_excerpt').value = element_values;
    }

    // INITIALIZE MAP WITH DEFAULT SETTINGS AND OBJECTS

    // Display map with default or existing values

    if (document.querySelector('input[name=myGmaps_center]').value == '') {
      var latlng = new google.maps.LatLng(43.0395797336425, 6.126280043989323);
      var default_zoom = '12';
      var default_type = 'roadmap';
    } else {
      var parts = document.querySelector('input[name=myGmaps_center]').value.split(",");
      var lat = parseFloat(trim(parts[0]));
      var lng = parseFloat(trim(parts[1]));
      var latlng = new google.maps.LatLng(lat, lng);
      var default_zoom = document.querySelector('input[name=myGmaps_zoom]').value;
      var default_type = document.querySelector('input[name=myGmaps_type]').value;
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
    for (i in styles_array) {
      value = styles_array[i].replace("_styles.js", "");
      mapTypeIds.push(value);

      const user_style = dotclear.getData(value);

      window[value] = new google.maps.StyledMapType(user_style.style, { name: user_style.name });
    }

    const myOptions = {
      mapId: "element-map",
      zoom: parseFloat(default_zoom),
      center: latlng,
      scrollwheel: false,
      mapTypeControl: true,
      overviewMapControl: true,
      streetViewControl: false,
      mapTypeControlOptions: {
        mapTypeIds
      }
    };

    map = new Map(document.getElementById("map_canvas"), myOptions);

    // Credit OSM if used ;)

    const credit = '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap Contributors</a>';

    const creditNode = document.createElement('div');
    creditNode.id = 'credit-control';
    creditNode.index = 0;

    if (default_type == 'roadmap') {
      map.setOptions({
        mapTypeId: google.maps.MapTypeId.ROADMAP
      });
    } else if (default_type == 'satellite') {
      map.setOptions({
        mapTypeId: google.maps.MapTypeId.SATELLITE
      });
    } else if (default_type == 'hybrid') {
      map.setOptions({
        mapTypeId: google.maps.MapTypeId.HYBRID
      });
    } else if (default_type == 'terrain') {
      map.setOptions({
        mapTypeId: google.maps.MapTypeId.TERRAIN
      });
    } else if (default_type == 'OpenStreetMap') {
      map.setOptions({
        mapTypeId: 'OpenStreetMap'
      });
      map.controls[google.maps.ControlPosition.BOTTOM_RIGHT].push(creditNode);
      creditNode.innerHTML = credit;

    } else {
      map.setOptions({
        mapTypeId: default_type
      });
    }

    map.mapTypes.set('neutral_blue', neutral_blue);

    map.mapTypes.set('OpenStreetMap', new google.maps.ImageMapType({
      getTileUrl(coord, zoom) {
        return `https://tile.openstreetmap.org/${zoom}/${coord.x}/${coord.y}.png`;
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
      map.mapTypes.set(mapTypeIds[i], value);
    }

    // Geocoding

    geocoder = new google.maps.Geocoder();

    function geocode() {
      const address = document.getElementById("address").value;
      geocoder.geocode({
        'address': address,
        'partialmatch': true
      }, geocodeResult);
    }

    function geocodeResult(results, status) {
      if (status == 'OK' && results.length > 0) {
        map.fitBounds(results[0].geometry.viewport);
      } else {
        alert(`Geocode was not successful for the following reason: ${status}`);
      }
    }

    document.getElementById('geocode').addEventListener('click', (e) => {
      e.preventDefault();
      geocode();
    });

    // Set default objects

    var markersArray = [];
    var vertexArray = [];

    var polyline;
    const polylineOptions = {
      strokeColor: '#555',
      strokeOpacity: 0.8,
      strokeWeight: 3,
      draggable: true,
      editable: true
    };
    polyline = new google.maps.Polyline(polylineOptions);
    var polylinePath = polyline.getPath();

    var polygon;
    const polygonOptions = {
      strokeColor: '#555',
      strokeOpacity: 0.8,
      strokeWeight: 3,
      fillColor: '#ccc',
      fillOpacity: 0.35,
      draggable: true,
      editable: true
    };
    polygon = new google.maps.Polygon(polygonOptions);
    var polygonPath = polygon.getPath();

    var rectangle;
    const rectangleOptions = {
      strokeColor: '#555',
      strokeOpacity: 0.8,
      strokeWeight: 3,
      fillColor: '#ccc',
      fillOpacity: 0.35,
      draggable: true,
      editable: true
    };
    rectangle = new google.maps.Rectangle(rectangleOptions);

    var circle;
    const circleOptions = {
      strokeColor: '#555',
      strokeOpacity: 0.8,
      strokeWeight: 3,
      fillColor: '#ccc',
      fillOpacity: 0.35,
      center: latlng,
      draggable: true,
      editable: true,
      radius: 1000
    };
    circle = new google.maps.Circle(circleOptions);

    var kmlLayer;
    kmlLayer = new google.maps.KmlLayer({});

    var geoRssLayer;
    geoRssLayer = new google.maps.KmlLayer({});

    const directionsService = new google.maps.DirectionsService();

    var polylineRendererOptions = {
      strokeColor: '#555',
      strokeOpacity: 0.8,
      strokeWeight: 3
    }
    var rendererOptions = {
      polylineOptions: polylineRendererOptions
    }
    var directionsDisplay = new google.maps.DirectionsRenderer(rendererOptions);


    var routePolyline;
    const routePolylineOptions = {
      strokeColor: '#555',
      strokeOpacity: 0,
      strokeWeight: 20,
      zIndex: 1
    };
    routePolyline = new google.maps.Polyline(routePolylineOptions);
    var routePolylinePath = routePolyline.getPath();

    const input = document.getElementById('address');
    const autocomplete = new google.maps.places.Autocomplete(input);

    // OBJECTS LISTENERS

    // Map listeners

    google.maps.event.addListener(map, 'click', (event) => {
      infowindow.close();

      let action = 'none';
      const hasButtonActive = (document.querySelectorAll(".map_toolbar button.active").length > 0 ? true : false);
      document.querySelectorAll(".map_toolbar button").forEach(button => {
        if (button.classList.contains("active")) {
          action = (button.id);
        } else if (hasButtonActive) {
          button.classList.add("inactive");
        }
      });

      if (action == 'add_marker') {
        if (document.getElementById('post_excerpt').value == '') {
          addMarker(event.latLng);
          document.getElementById("delete_map").disabled = false;
        }
      } else if (action == 'add_polyline') {
        addPolylineVertex(event.latLng);
        document.getElementById("delete_map").disabled = false;

      } else if (action == 'add_polygon') {
        addPolygonVertex(event.latLng);
        document.getElementById("delete_map").disabled = false;

      } else if (action == 'add_kml') {
        if (document.getElementById('post_excerpt').value == '') {
          addKml(event.latLng);
          document.getElementById("delete_map").disabled = false;
        }
      } else if (action == 'add_georss') {
        if (document.getElementById('post_excerpt').value == '') {
          addgeoRSS(event.latLng);
          document.getElementById("delete_map").disabled = false;
        }
      } else if (action == 'add_rectangle') {
        if (document.getElementById('post_excerpt').value == '') {
          addRectangle(event.latLng);
          document.getElementById("delete_map").disabled = false;
        }
      } else if (action == 'add_circle') {
        if (document.getElementById('post_excerpt').value == '') {
          addCircle(event.latLng);
          document.getElementById("delete_map").disabled = false;
        }
      } else if (action == 'add_directions' && document.getElementById('post_excerpt').value == '') {
        addDirections(event.latLng);
        document.getElementById("delete_map").disabled = false;
      }
    });


    google.maps.event.addListener(map, 'maptypeid_changed', () => {
      if (map.getMapTypeId() == 'OpenStreetMap') {
        map.controls[google.maps.ControlPosition.BOTTOM_RIGHT].push(creditNode);
        creditNode.innerHTML = credit;
      } else {
        map.controls[google.maps.ControlPosition.BOTTOM_RIGHT].clear(creditNode);
      }
    });

    // Avoid some events too often firing bug (?)
    function debounce(fn, time) {
      let timeout;
      return function () {
        const args = arguments;
        const functionCall = () => fn.apply(this, args);
        clearTimeout(timeout);
        timeout = setTimeout(functionCall, time);
      }
    }

    // Polyline listeners

    google.maps.event.addListener(polyline, 'rightclick', (mev) => {
      if (mev.vertex != null) {
        polyline.getPath().removeAt(mev.vertex);
      }
    });

    google.maps.event.addListener(polylinePath, 'insert_at', debounce(() => {
      updatePolyline();
    }, 250));

    google.maps.event.addListener(polylinePath, 'remove_at', debounce(() => {
      updatePolyline();
    }, 250));

    google.maps.event.addListener(polylinePath, 'set_at', debounce(() => {
      updatePolyline();
    }, 250));

    google.maps.event.addListener(polyline, 'click', function (event) {
      const infowindowPolyline =
        '<div id="infowindow_polyline" class="col">' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '"></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '"></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '"></p>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowPolyline);
      infowindow.open(map);

    });

    // Polygon listeners

    google.maps.event.addListener(polygon, 'rightclick', (mev) => {
      if (mev.vertex != null) {
        polygon.getPath().removeAt(mev.vertex);
      }
    });

    google.maps.event.addListener(polygonPath, 'insert_at', debounce(() => {
      updatePolygon();
    }, 250));

    google.maps.event.addListener(polygonPath, 'remove_at', debounce(() => {
      updatePolygon();
    }, 250));

    google.maps.event.addListener(polygonPath, 'set_at', debounce(() => {
      updatePolygon();
    }, 250));

    google.maps.event.addListener(polygon, 'click', function (event) {
      const infowindowPolygon =
        '<div id="infowindow_polygon">' +
        '<div class="two-boxes"' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '"></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '"></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '"></p>' +
        '</div>' +
        '<div class="two-boxes"' +
        '<p><label for="fill_color">' + fill_color_msg + '</label><input type="text" id="fill_color" size="10" value="' + this.fillColor + '"></p>' +
        '<p><label for="fill_opacity">' + fill_opacity_msg + '</label><input type="text" id="fill_opacity" size="10" value="' + this.fillOpacity + '"></p>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowPolygon);
      infowindow.open(map);

    });

    // Rectangle listeners


    google.maps.event.addListener(rectangle, 'bounds_changed', debounce(() => {
      updateRectangle();
    }, 250));

    google.maps.event.addListener(rectangle, 'dragend', debounce(() => {
      updateRectangle();
    }, 250));

    google.maps.event.addListener(rectangle, 'click', function (event) {
      const infowindowRectangle =
        '<div id="infowindow_rectangle">' +
        '<div class="two-boxes"' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '"></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '"></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '"></p>' +
        '</div>' +
        '<div class="two-boxes"' +
        '<p><label for="fill_color">' + fill_color_msg + '</label><input type="text" id="fill_color" size="10" value="' + this.fillColor + '"></p>' +
        '<p><label for="fill_opacity">' + fill_opacity_msg + '</label><input type="text" id="fill_opacity" size="10" value="' + this.fillOpacity + '"></p>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowRectangle);
      infowindow.open(map);
    });

    //Circle listeners

    google.maps.event.addListener(circle, 'center_changed', debounce(() => {
      updateCircle();
    }, 250));

    google.maps.event.addListener(circle, 'radius_changed', debounce(() => {
      updateCircle();
    }, 250));

    google.maps.event.addListener(circle, 'click', function (event) {
      const infowindowCircle =
        '<div id="infowindow_circle">' +
        '<div class="two-boxes"' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '"></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '"></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '"></p>' +
        '</div>' +
        '<div class="two-boxes"' +
        '<p><label for="fill_color">' + fill_color_msg + '</label><input type="text" id="fill_color" size="10" value="' + this.fillColor + '"></p>' +
        '<p><label for="fill_opacity">' + fill_opacity_msg + '</label><input type="text" id="fill_opacity" size="10" value="' + this.fillOpacity + '"></p>' +
        '<p><label for="circle_radius">' + circle_radius_msg + '</label><input type="text" id="circle_radius" size="10" value="' + this.radius + '"></p>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowCircle);
      infowindow.open(map);
    });

    //Kml listener

    google.maps.event.addListener(kmlLayer, 'click', (event) => {
      const myKmls = [];
      if (document.getElementById("kmls_list").value != '') {
        const kmls_base_url = document.getElementById("kmls_base_url").value;
        const kmls_list = document.getElementById("kmls_list").value;
        const kmls_array = kmls_list.split(',');
        for (i in kmls_array) {
          this_kml = `<li>${kmls_array[i]}</li>`;
          myKmls.push(this_kml);
        }
      }

      let custom_kmls = myKmls.join();
      custom_kmls = `<ul>${custom_kmls.replace(/\,/g, '')}</ul>`;

      if (myKmls != '') {
        var has_custom_kmls = `<h4>${custom_kmls_msg}</h4>` +
          '<div style="max-height: 100px;overflow: auto">' +
          custom_kmls +
          '</div>' +
          '<hr>';
      } else {
        var has_custom_kmls = '';
      }

      const infowindowKml =
        '<div id="infowindow_kml" style="cursor: pointer">' +
        has_custom_kmls +
        '<h4>' + kml_url_msg + '</h4>' +
        '<p><input type="text" id="kml_url" size="80" value="' + document.getElementById('post_excerpt').value + '"></p>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowKml);
      infowindow.open(map);
    });

    // GeoRSS listener

    google.maps.event.addListener(geoRssLayer, 'click', (event) => {
      const infowindowgeoRss =
        '<div id="infowindow_georss" style="cursor: pointer">' +
        '<h4>' + geoRss_url_msg + '</h4>' +
        '<p><input type="text" id="geoRss_url" size="80" value="' + document.getElementById('post_excerpt').value + '"></p>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowgeoRss);
      infowindow.open(map);
    });

    // directions listener

    google.maps.event.addListener(polylinePath, 'set_at', debounce(() => {
      updatePolyline();
    }, 250));

    google.maps.event.addListener(routePolyline, 'click', (event) => {
      const parts = element_values.split("|");

      const start = parts[0];
      const end = parts[1];
      const weight = parts[2];
      const opacity = parts[3];
      const color = parts[4];
      const show = parts[5];

      if (show == 'true') {
        var state = 'checked = "checked"';
      } else {
        var state = '';
      }

      const infowindowDirections =
        '<div id="infowindow_directions" style="cursor: pointer">' +
        '<div class="two-cols clearfix">' +
        '<div class="col70">' +
        '<p><label for="directions_start">' + directions_start_msg + '</label><input type="text" id="directions_start" size="40" value="' + start + '"></p>' +
        '<p><label for="directions_end">' + directions_end_msg + '</label><input type="text" id="directions_end" size="40" value="' + end + '"></p>' +
        '<p><label for="directions_show"><input type="checkbox" id="directions_show" ' + state + '>' + directions_show_msg + '</label></p>' +
        '</div>' +
        '<div class="col30">' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + color + '">' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + opacity + '">' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + weight + '">' +
        '</div>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK">' +
        '</div>';

      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowDirections);
      infowindow.open(map);

      // Initialize autocomplete for directions_start and directions_end fields after infowindow is opened
      google.maps.event.addListenerOnce(infowindow, 'domready', () => {
        const startInput = document.getElementById('directions_start');
        const endInput = document.getElementById('directions_end');
        new google.maps.places.Autocomplete(startInput);
        new google.maps.places.Autocomplete(endInput);
      });

    });

    // INFOWINDOW SETTINGS AND ACTIONS

    var infowindow = new google.maps.InfoWindow({});

    // Icons infowindow

    const myIcons = [];
    if (document.getElementById("icons_list").value != '') {
      const icons_base_url = document.getElementById("icons_base_url").value;
      const icons_list = document.getElementById("icons_list").value;
      const icons_array = icons_list.split(',');
      for (i in icons_array) {
        const this_icon = `<img src="${icons_base_url}${icons_array[i]}" alt="${icons_array[i]}">`;
        myIcons.push(this_icon);
      }
    }

    let custom_icons = myIcons.join();
    custom_icons = custom_icons.replace(/\,/g, '');

    var default_icons_url = document.getElementById("plugin_QmarkURL").value;

    if (custom_icons != '') {
      var has_custom_icons = `<h4>${custom_icons_msg}</h4>` +
        '<div id="custom_icons_list">' +
        custom_icons +
        '</div>' +
        '<hr>';
    } else {
      var has_custom_icons = '';
    }

    var infowindowIcons =
      '<div id="infowindow_icons" style="cursor: pointer">' +
      has_custom_icons +
      '<div id="default_icons_list">' +
      '<h4>' + default_icons_msg + '</h4>' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-blue.png" alt="marker-blue.png" >' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-green.png" alt="marker-green.png" >' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-grey.png" alt="marker-grey.png" >' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-orange.png" alt="marker-orange.png" >' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-purple.png" alt="marker-purple.png" >' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-yellow.png" alt="marker-yellow.png" >' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker.png" alt="marker.png" >&nbsp;' +
      '</div>' +
      '</div>';

    // Infowindows actions

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_icons img')) {
        let element_values = document.getElementById('post_excerpt').value;
        const parts = element_values.split("|");
        const lat = parseFloat(parts[0]);
        const lng = parseFloat(parts[1]);
        marker.setIcon(event.target.src);
        const icon = event.target.src;
        element_values = `${marker.position.lat()}|${marker.position.lng()}|${icon}`;
        document.getElementById('post_excerpt').value = element_values;
        infowindow.close();
      }
    });

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_polyline #save')) {
        const color = document.getElementById('stroke_color').value;
        const opacity = document.getElementById('stroke_opacity').value;
        const weight = document.getElementById('stroke_weight').value;
        polyline.setOptions({
          strokeColor: color,
          strokeOpacity: parseFloat(opacity),
          strokeWeight: parseFloat(weight)
        });

        updatePolyline();

        infowindow.close();
      }
    });

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_polygon #save')) {
        const color = document.getElementById('stroke_color').value;
        const opacity = document.getElementById('stroke_opacity').value;
        const weight = document.getElementById('stroke_weight').value;
        const fill_color = document.getElementById('fill_color').value;
        const fill_opacity = document.getElementById('fill_opacity').value;
        polygon.setOptions({
          strokeColor: color,
          strokeOpacity: parseFloat(opacity),
          strokeWeight: parseFloat(weight),
          fillColor: fill_color,
          fillOpacity: parseFloat(fill_opacity)
        });

        updatePolygon();

        infowindow.close();
      }
    });

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_rectangle #save')) {
        const weight = document.getElementById('stroke_weight').value;
        const opacity = document.getElementById('stroke_opacity').value;
        const color = document.getElementById('stroke_color').value;
        const fill_color = document.getElementById('fill_color').value;
        const fill_opacity = document.getElementById('fill_opacity').value;

        rectangle.setOptions({
          strokeColor: color,
          strokeOpacity: parseFloat(opacity),
          strokeWeight: parseFloat(weight),
          fillColor: fill_color,
          fillOpacity: parseFloat(fill_opacity)
        });

        updateRectangle();

        infowindow.close();
      }
    });

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_circle #save')) {
        const weight = document.getElementById('stroke_weight').value;
        const opacity = document.getElementById('stroke_opacity').value;
        const color = document.getElementById('stroke_color').value;
        const fill_color = document.getElementById('fill_color').value;
        const fill_opacity = document.getElementById('fill_opacity').value;
        const radius = document.getElementById('circle_radius').value;

        circle.setOptions({
          strokeColor: color,
          strokeOpacity: parseFloat(opacity),
          strokeWeight: parseFloat(weight),
          fillColor: fill_color,
          fillOpacity: parseFloat(fill_opacity),
          radius: parseFloat(radius)
        });

        updateCircle();

        infowindow.close();
      }
    });

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_kml li')) {
        const li_clicked_url = document.getElementById("kmls_base_url").value + event.target.textContent;
        document.getElementById('kml_url').value = li_clicked_url;
        document.getElementById('kml_url').focus();
      }
    });

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_kml #save')) {
        kmlLayer.setMap(null);
        const url = document.getElementById('kml_url').value;
        if (url != null && url != '' && is_url(url)) {
          kmlLayer.setOptions({
            url,
            preserveViewport: true,
            suppressInfoWindows: true
          });

          // Save values and type

          document.getElementById('element_type').value = 'included kml file';
          document.getElementById('post_excerpt').value = url;
        }

        kmlLayer.setMap(map);
        infowindow.close();
      }
    });

    document.addEventListener('click', (event) => {
      if (event.target.matches('#infowindow_georss #save')) {
        geoRssLayer.setMap(null);
        const url = document.getElementById('geoRss_url').value;
        if (url != null && url != '' && is_url(url)) {
          geoRssLayer.setOptions({
            url,
            preserveViewport: true,
            suppressInfoWindows: true
          });

          // Save values and type

          document.getElementById('element_type').value = 'GeoRSS feed';
          document.getElementById('post_excerpt').value = url;
        }

        geoRssLayer.setMap(map);
        infowindow.close();
      }
    });

    document.addEventListener('click', (event) => {

      if (event.target.matches('#infowindow_directions #save')) {
        const start = document.getElementById('directions_start').value;
        const end = document.getElementById('directions_end').value;
        const show = document.getElementById('directions_show').checked;
        const color = document.getElementById('stroke_color').value;
        const opacity = document.getElementById('stroke_opacity').value;
        const weight = document.getElementById('stroke_weight').value;

        const polylineRendererOptions = {
          strokeColor: color,
          strokeOpacity: opacity,
          strokeWeight: weight
        };

        const rendererOptions = {
          polylineOptions: polylineRendererOptions
        };

        const request = {
          origin: start,
          destination: end,
          travelMode: google.maps.TravelMode.DRIVING
        };

        directionsService.route(request, (result, status) => {
          if (status == google.maps.DirectionsStatus.OK) {
            const routePath = result.routes[0].overview_path;
            routePolyline.setPath(routePath);
            directionsDisplay.setOptions({
              options: rendererOptions
            });
            directionsDisplay.setDirections(result);
            directionsDisplay.setMap(map);
          }

        });

        // Save values and type

        element_values = `${start}|${end}|${weight}` +
          "|" + opacity + "|" + color + "|" + show;

        document.getElementById('element_type').value = 'directions';
        document.getElementById('post_excerpt').value = element_values;

        directionsDisplay.setMap(map);
        routePolyline.setMap(map);
        infowindow.close();
      }
    });

    // PLACE EXISTING ELEMENTS

    var element_values = document.getElementById('post_excerpt').value;
    const element_type = document.querySelector('input[name=element_type]').value;


    // Place existing marker if any

    if (element_type == 'point of interest') {
      var parts = element_values.split("|");
      var lat = parseFloat(parts[0]);
      var lng = parseFloat(parts[1]);
      const icon = parts[2];
      var location = new google.maps.LatLng(lat, lng);
      const Img = document.createElement('img');
      Img.src = icon;

      marker = new google.maps.marker.AdvancedMarkerElement({
        position: location,
        draggable: true,
        content: Img,
        map
      });
      markersArray.push(marker);
      document.getElementById('add_marker').classList.add("active");
      infowindow.setContent(infowindowIcons);

      // Listeners

      google.maps.event.addListener(marker, 'click', function () {
        infowindow.open(map, this);
      });

      google.maps.event.addListener(marker, "dragend", () => {
        element_values = `${marker.position.lat()}|${marker.position.lng()}|${icon}`
        document.getElementById('post_excerpt').value = element_values;
      });

      // Place existing polyline if any

    } else if (element_type == 'polyline') {
      var lines = element_values.split("\n");
      for (var i = 0; i < lines.length - 1; i++) {
        var parts = lines[i].split("|");
        var lat = parseFloat(parts[0]);
        var lng = parseFloat(parts[1]);
        var location = new google.maps.LatLng(lat, lng);
        polylinePath.push(location);
      }

      const polyline_options = lines.pop();
      var parts = polyline_options.split("|");
      var weight = parseFloat(parts[0]);
      var opacity = parseFloat(parts[1]);
      var color = parts[2];

      polyline.setOptions({
        strokeColor: color,
        strokeOpacity: parseFloat(opacity),
        strokeWeight: parseFloat(weight)
      });

      polylinePath = polyline.getPath();

      polyline.setMap(map);
      document.getElementById('add_polyline').classList.add("active");

      // Place existing polygon if any

    } else if (element_type == 'polygon') {
      var lines = element_values.split("\n");
      for (var i = 0; i < lines.length - 1; i++) {
        var parts = lines[i].split("|");
        var lat = parseFloat(parts[0]);
        var lng = parseFloat(parts[1]);
        var location = new google.maps.LatLng(lat, lng);
        polygonPath.push(location);
      }

      const polygon_options = lines.pop();
      var parts = polygon_options.split("|");
      var weight = parseFloat(parts[0]);
      var opacity = parseFloat(parts[1]);
      var color = parts[2];
      var fill_color = parts[3];
      var fill_opacity = parseFloat(parts[4]);

      polygon.setOptions({
        strokeColor: color,
        strokeOpacity: parseFloat(opacity),
        strokeWeight: parseFloat(weight),
        fillColor: fill_color,
        fillOpacity: parseFloat(fill_opacity)
      });

      polygonPath = polygon.getPath();

      polygon.setMap(map);
      document.getElementById('add_polygon').classList.add("active");

      // Place existing rectangle if any

    } else if (element_type == 'rectangle') {
      var lines = element_values.split("\n");

      var parts = lines[0].split("|");
      const swlat = parseFloat(parts[0]);
      const nelng = parseFloat(parts[1]);
      const nelat = parseFloat(parts[2]);
      const swlng = parseFloat(parts[3]);
      bounds = new google.maps.LatLngBounds(
        new google.maps.LatLng(swlat, nelng),
        new google.maps.LatLng(nelat, swlng));

      rectangle.setBounds(bounds);

      var parts2 = lines[1].split("|");
      var weight = parseFloat(parts2[0]);
      var opacity = parseFloat(parts2[1]);
      var color = parts2[2];
      var fill_color = parts2[3];
      var fill_opacity = parseFloat(parts2[4]);

      rectangle.setOptions({
        strokeColor: color,
        strokeOpacity: parseFloat(opacity),
        strokeWeight: parseFloat(weight),
        fillColor: fill_color,
        fillOpacity: parseFloat(fill_opacity)
      });

      document.getElementById('add_rectangle').classList.add("active");
      rectangle.setMap(map);

      // Place existing circle if any

    } else if (element_type == 'circle') {
      var lines = element_values.split("\n");

      var parts = lines[0].split("|");
      var lat = parseFloat(parts[0]);
      var lng = parseFloat(parts[1]);
      const radius = parseFloat(parts[2]);
      var location = new google.maps.LatLng(lat, lng);

      var parts2 = lines[1].split("|");
      var weight = parseFloat(parts2[0]);
      var opacity = parseFloat(parts2[1]);
      var color = parts2[2];
      var fill_color = parts2[3];
      var fill_opacity = parseFloat(parts2[4]);

      circle.setOptions({
        strokeColor: color,
        strokeOpacity: parseFloat(opacity),
        strokeWeight: parseFloat(weight),
        fillColor: fill_color,
        fillOpacity: parseFloat(fill_opacity),
        center: location,
        radius: parseFloat(radius)
      });

      document.getElementById('add_circle').classList.add("active");
      circle.setMap(map);

      // Place existing kml if any

    } else if (element_type == 'included kml file') {

      kmlLayer.setOptions({
        url: element_values,
        preserveViewport: true,
        suppressInfoWindows: true
      });

      document.getElementById('add_kml').classList.add("active");
      kmlLayer.setMap(map);

      // Place existing geoRSS if any

    } else if (element_type == 'GeoRSS feed') {

      geoRssLayer.setOptions({
        url: element_values,
        preserveViewport: true,
        suppressInfoWindows: true
      });

      document.getElementById('add_georss').classList.add("active");
      geoRssLayer.setMap(map);

      // Place existing directions if any

    } else if (element_type == 'directions') {
      var parts = element_values.split("|");

      const start = parts[0];
      const end = parts[1];
      var weight = parts[2];
      var opacity = parts[3];
      var color = parts[4];

      var polylineRendererOptions = {
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight
      }

      var rendererOptions = {
        polylineOptions: polylineRendererOptions
      }

      const request = {
        origin: start,
        destination: end,
        travelMode: google.maps.TravelMode.DRIVING
      };

      directionsService.route(request, (result, status) => {
        if (status == google.maps.DirectionsStatus.OK) {
          const routePath = result.routes[0].overview_path;
          routePolyline.setPath(routePath);
          directionsDisplay.setOptions({
            options: rendererOptions
          });
          directionsDisplay.setDirections(result);
          directionsDisplay.setMap(map);

        }
      });

      document.getElementById('add_directions').classList.add("active");
      routePolyline.setMap(map);
    }

    // ADD NEW OBJECT OR VERTEX POINT

    // Add marker

    function addMarker(location) {

      // Initialize
      const Img = document.createElement('img');
      Img.src = `${default_icons_url}pf=myGmaps/icons/marker.png`;


      marker = new google.maps.marker.AdvancedMarkerElement({
        position: location,
        content: Img,
        draggable: true,
        map
      });
      markersArray.push(marker);

      infowindow.setContent(infowindowIcons);

      // Listeners

      google.maps.event.addListener(marker, 'click', function () {
        infowindow.open(map, this);
      });

      google.maps.event.addListener(marker, "dragend", () => {
        element_values = `${marker.position.lat()}|${marker.position.lng()}|${marker.icon}`;
        document.getElementById('post_excerpt').value = element_values;
      });

      // Save values

      element_values = `${marker.position.lat()}|${marker.position.lng()}|${marker.icon}`;

      document.getElementById('element_type').value = 'point of interest';
      document.getElementById('post_excerpt').value = element_values;

    }

    // Add polyline vertex

    function addPolylineVertex(location) {

      // Add point to vertex array

      polylinePath.push(location);
      polyline.setMap(map);

      // Save values

      updatePolyline();

    }

    // Add polygon vertex

    function addPolygonVertex(location) {

      // Add point to vertex array

      polygonPath.push(location);
      polygon.setMap(map);

      // Save values

      updatePolygon();

    }

    // Add rectangle

    function addRectangle(location) {
      // Initialize
      const scale = 2 ** map.getZoom();

      rectangle.setBounds(new google.maps.LatLngBounds(
        new google.maps.LatLng(((location.lat() * scale) - 50) / scale, ((location.lng() * scale) - 75) / scale),
        new google.maps.LatLng(((location.lat() * scale) + 50) / scale, ((location.lng() * scale) + 75) / scale)));

      rectangle.setMap(map);

      // Save values

      updateRectangle();

    }

    // Add circle

    function addCircle(location) {
      // Initialize

      const scale = 2 ** ((Math.PI * 10) - map.getZoom());
      const radius = scale / 500;
      circle.setOptions({
        radius,
        center: location
      });
      circle.setMap(map);

      // Save values

      updateCircle();

    }

    // Add kml

    function addKml(location) {
      const myKmls = [];
      if (document.getElementById("kmls_list").value != '') {
        const kmls_base_url = document.getElementById("kmls_base_url").value;
        const kmls_list = document.getElementById("kmls_list").value;
        const kmls_array = kmls_list.split(',');
        for (i in kmls_array) {
          this_kml = `<li>${kmls_array[i]}</li>`;
          myKmls.push(this_kml);
        }
      }

      let custom_kmls = myKmls.join();
      custom_kmls = `<ul>${custom_kmls.replace(/\,/g, '')}</ul>`;

      if (myKmls != '') {
        var has_custom_kmls = `<h4>${custom_kmls_msg}</h4>` +
          '<div style="max-height: 100px;overflow: auto">' +
          custom_kmls +
          '</div>' +
          '<hr>';
      } else {
        var has_custom_kmls = '';
      }

      const infowindowKml =
        '<div id="infowindow_kml" style="cursor: pointer">' +
        has_custom_kmls +
        '<h4>' + kml_url_msg + '</h4>' +
        '<p><input type="text" id="kml_url" size="80" value="' + document.getElementById('post_excerpt').value + '"></p>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(location);
      infowindow.setContent(infowindowKml);
      infowindow.open(map);
    }

    // Add geoRSS

    function addgeoRSS(location) {
      const infowindowgeoRss =
        '<div id="infowindow_georss" style="cursor: pointer">' +
        '<h4>' + geoRss_url_msg + '</h4>' +
        '<p><input type="text" id="geoRss_url" size="80" value="' + document.getElementById('post_excerpt').value + '"></p>' +
        '<p><input type="button" id="save" value="OK"></p>' +
        '</div>';
      infowindow.setPosition(location);
      infowindow.setContent(infowindowgeoRss);
      infowindow.open(map);
    }

    // Add directions

    function addDirections(location) {
      const color = '#555';
      const opacity = 0.8;
      const weight = 3;

      const infowindowDirections =
        '<div id="infowindow_directions" style="cursor: pointer">' +
        '<div class="two-cols clearfix">' +
        '<div class="col70">' +
        '<p><label for="directions_start">' + directions_start_msg + '</label><input type="text" id="directions_start" size="40" value=""></p>' +
        '<p><label for="directions_end">' + directions_end_msg + '</label><input type="text" id="directions_end" size="40" value=""></p>' +
        '<p><label for="directions_show"><input type="checkbox" id="directions_show">' + directions_show_msg + '</label></p>' +
        '</div>' +
        '<div class="col30">' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + color + '"></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + opacity + '"></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + weight + '"></p>' +
        '</div>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK">' +
        '</div>';

      infowindow.setPosition(location);
      infowindow.setContent(infowindowDirections);
      infowindow.open(map);

      // Initialize autocomplete for directions_start and directions_end fields after infowindow is opened
      google.maps.event.addListenerOnce(infowindow, 'domready', () => {
        const startInput = document.getElementById('directions_start');
        const endInput = document.getElementById('directions_end');
        new google.maps.places.Autocomplete(startInput);
        new google.maps.places.Autocomplete(endInput);
      });
    }


    // DELETE EXISTING ELEMENT

    function deleteMap() {

      document.querySelectorAll(".map_toolbar button").forEach(button => {
        button.classList.remove("active");
        button.classList.add("inactive");
      });

      document.getElementById("delete_map").disabled = true;

      for (i in markersArray) {
        markersArray[i].setMap(null);
      }

      markersArray = [];
      vertexArray = [];

      polyline.setOptions({});
      polyline.setMap(null);

      polygon.setOptions({});
      polygon.setMap(null);

      rectangle.setOptions({});
      rectangle.setMap(null);

      circle.setOptions({});
      circle.setMap(null);

      kmlLayer.setOptions({});
      kmlLayer.setMap(null);

      geoRssLayer.setOptions({});
      geoRssLayer.setMap(null);

      routePolyline.setMap(null);
      directionsDisplay.setMap(null);

      document.getElementById('element_type').value = '';
      document.getElementById('post_excerpt').value = '';

    }

    // SUBMIT FORM AND SAVE ELEMENT

    document.getElementById('entry-form').addEventListener('submit', () => {
      const element_type = document.getElementById('element_type').value;
      if (element_type === '') {
        document.getElementById('element_type').value = 'notype';
      }

      const default_location = `${map.getCenter().lat()},${map.getCenter().lng()}`;
      const default_zoom = map.getZoom();
      const default_type = map.getMapTypeId();

      document.querySelector('input[name=myGmaps_center]').value = default_location;
      document.querySelector('input[name=myGmaps_zoom]').value = default_zoom;
      document.querySelector('input[name=myGmaps_type]').value = default_type;
      return true;
    });
  }

  initMap();

});