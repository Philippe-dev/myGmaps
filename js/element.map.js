$(function () {

  if (!document.getElementById) {
    return;
  }

  if (document.getElementById('edit-entry')) {

    // Misc functions

    function trim(myString) {
      return myString.replace(/^\s+/g, '').replace(/\s+$/g, '')
    }

    function is_url(str) {
      var exp = new RegExp("^(http://)[a-zA-Z0-9.-]*[a-zA-Z0-9/_-]", "g");
      return exp.test(str);
    }

    // Toolbar actions

    $(".map_toolbar button").on('click', function () {

      if ($(this).attr('id') == 'delete_map') {
        deleteMap();
      } else {
        if ($('#post_excerpt').val() == '') {
          $(".map_toolbar button").each(function () {
            $(this).removeClass("active");
          });
          $(this).addClass("active");
        }
      }
    });

    // OBJECTS UPDATE FUNCTIONS

    function updatePolyline() {
      vertexArray.length = 0;
      var len = polylinePath.getLength();
      for (var i = 0; i < len; i++) {
        vertexArray.push(polylinePath.getAt(i).lat() + "|" + polylinePath.getAt(i).lng());
      }
      element_values = vertexArray.join('\n');
      element_values = element_values + '\n' + polyline.strokeWeight +
        "|" + polyline.strokeOpacity + "|" + polyline.strokeColor;
      $('#element_type').val('polyline');
      $('#post_excerpt').val(element_values);
    }

    function updatePolygon() {
      vertexArray.length = 0;
      var len = polygonPath.getLength();
      for (var i = 0; i < len; i++) {
        vertexArray.push(polygonPath.getAt(i).lat() + "|" + polygonPath.getAt(i).lng());
      }
      element_values = vertexArray.join('\n');
      element_values = element_values + '\n' + polygon.strokeWeight +
        "|" + polygon.strokeOpacity + "|" + polygon.strokeColor +
        "|" + polygon.fillColor + "|" + polygon.fillOpacity;
      $('#element_type').val('polygon');
      $('#post_excerpt').val(element_values);
    }

    function updateRectangle() {
      var square = rectangle.getBounds();
      var NE = square.getNorthEast();
      var SW = square.getSouthWest();
      var ne = new google.maps.LatLng(NE.lat(), SW.lng());
      var sw = new google.maps.LatLng(SW.lat(), NE.lng());
      element_values = sw.lat() + "|" + ne.lng() + "|" + ne.lat() + "|" + sw.lng();
      element_values = element_values + '\n' + rectangle.strokeWeight +
        "|" + rectangle.strokeOpacity + "|" + rectangle.strokeColor +
        "|" + rectangle.fillColor + "|" + rectangle.fillOpacity;
      $('#element_type').val('rectangle');
      $('#post_excerpt').val(element_values);
    }

    function updateCircle() {
      var center = circle.getCenter();
      var radius = circle.getRadius();

      element_values = center.lat() + "|" + center.lng() + "|" + radius;
      element_values = element_values + '\n' + circle.strokeWeight +
        "|" + circle.strokeOpacity + "|" + circle.strokeColor +
        "|" + circle.fillColor + "|" + circle.fillOpacity;
      $('#element_type').val('circle');
      $('#post_excerpt').val(element_values);
    }

    // INITIALIZE MAP WITH DEFAULT SETTINGS AND OBJECTS

    // Display map with default or existing values

    if ($('input[name=myGmaps_center]').val() == '') {
      var latlng = new google.maps.LatLng(43.0395797336425, 6.126280043989323);
      var default_zoom = '12';
      var default_type = 'roadmap';
    } else {
      var parts = $('input[name=myGmaps_center]').val().split(",");
      var lat = parseFloat(trim(parts[0]));
      var lng = parseFloat(trim(parts[1]));
      var latlng = new google.maps.LatLng(lat, lng);
      var default_zoom = $('input[name=myGmaps_zoom]').val();
      var default_type = $('input[name=myGmaps_type]').val();
    }

    // Map styles. Get more styles from http://snazzymaps.com/

    var mapTypeIds = [google.maps.MapTypeId.ROADMAP,
      google.maps.MapTypeId.HYBRID,
      google.maps.MapTypeId.SATELLITE,
      google.maps.MapTypeId.TERRAIN,
      'OpenStreetMap',
      'neutral_blue'
    ];

    var map_styles_list = $('#map_styles_list').val();
    var styles_array = map_styles_list.split(',');
    for (i in styles_array) {
      value = styles_array[i].replace("_styles.js", "");
      mapTypeIds.push(value);
    }

    var myOptions = {
      zoom: parseFloat(default_zoom),
      center: latlng,
      scrollwheel: false,
      mapTypeControl: true,
      overviewMapControl: true,
      mapTypeControlOptions: {
        mapTypeIds: mapTypeIds
      }
    };

    var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);

    // Credit OSM if we can ;)

    var credit = '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap Contributors</a>';

    var creditNode = document.createElement('div');
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
      map.mapTypes.set(mapTypeIds[i], value);
    }

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
        map.fitBounds(results[0].geometry.viewport);
      } else {
        alert("Geocode was not successful for the following reason: " + status);
      }
    }

    $('#geocode').on('click', function () {
      geocode();
      return false;

    });

    // Set default objects

    var markersArray = [];
    var vertexArray = [];

    var polyline;
    var polylineOptions = {
      strokeColor: '#555',
      strokeOpacity: 0.8,
      strokeWeight: 3,
      draggable: true,
      editable: true
    };
    polyline = new google.maps.Polyline(polylineOptions);
    var polylinePath = polyline.getPath();

    var polygon;
    var polygonOptions = {
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
    var rectangleOptions = {
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
    var circleOptions = {
      strokeColor: '#555',
      strokeOpacity: 0.8,
      strokeWeight: 3,
      fillColor: '#ccc',
      fillOpacity: 0.35,
      center: latlng,
      draggable: true,
      editable: true
    };
    circle = new google.maps.Circle(circleOptions);

    var kmlLayer;
    kmlLayer = new google.maps.KmlLayer({});

    var geoRssLayer;
    geoRssLayer = new google.maps.KmlLayer({});

    var directionsService = new google.maps.DirectionsService();

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
    var routePolylineOptions = {
      strokeColor: '#555',
      strokeOpacity: 0,
      strokeWeight: 20,
      zIndex: 1
    };
    routePolyline = new google.maps.Polyline(routePolylineOptions);
    var routePolylinePath = routePolyline.getPath();

    var input = document.getElementById('address');
    var autocomplete = new google.maps.places.Autocomplete(input);

    // OBJECTS LISTENERS

    // Map listeners

    google.maps.event.addListener(map, 'click', function (event) {
      infowindow.close();

      var action = 'none';
      var hasButtonActive = ($(".map_toolbar button.active").length > 0 ? true : false);
      $(".map_toolbar button").each(function () {
        if ($(this).hasClass("active")) {
          action = ($(this).attr('id'));
        } else if (hasButtonActive) {
          $(this).addClass("inactive");
        }
      });

      if (action == 'add_marker') {
        if ($('#post_excerpt').val() == '') {
          addMarker(event.latLng);
        }
      } else if (action == 'add_polyline') {
        addPolylineVertex(event.latLng);

      } else if (action == 'add_polygon') {
        addPolygonVertex(event.latLng);

      } else if (action == 'add_kml') {
        if ($('#post_excerpt').val() == '') {
          addKml(event.latLng);
        }
      } else if (action == 'add_georss') {
        if ($('#post_excerpt').val() == '') {
          addgeoRSS(event.latLng);
        }
      } else if (action == 'add_rectangle') {
        if ($('#post_excerpt').val() == '') {
          addRectangle(event.latLng);
        }
      } else if (action == 'add_circle') {
        if ($('#post_excerpt').val() == '') {
          addCircle(event.latLng);
        }
      } else if (action == 'add_directions') {
        if ($('#post_excerpt').val() == '') {
          addDirections(event.latLng);
        }
      }
    });


    google.maps.event.addListener(map, 'maptypeid_changed', function () {
      if (map.getMapTypeId() == 'OpenStreetMap') {
        map.controls[google.maps.ControlPosition.BOTTOM_RIGHT].push(creditNode);
        creditNode.innerHTML = credit;
      } else {
        map.controls[google.maps.ControlPosition.BOTTOM_RIGHT].clear(creditNode);
      }
    });


    // Polyline listeners

    google.maps.event.addListener(polyline, 'rightclick', function (mev) {
      if (mev.vertex != null) {
        polyline.getPath().removeAt(mev.vertex);
      }
    });

    google.maps.event.addListener(polylinePath, 'insert_at', function () {
      updatePolyline();
    });

    google.maps.event.addListener(polylinePath, 'remove_at', function () {
      updatePolyline();
    });

    google.maps.event.addListener(polylinePath, 'set_at', function () {
      updatePolyline();
    });

    google.maps.event.addListener(polyline, 'click', function (event) {

      var infowindowPolyline =
        '<div id="infowindow_polyline" class="col">' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '" /></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '" /></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '" /></p>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowPolyline);
      infowindow.open(map);

    });

    // Polygon listeners

    google.maps.event.addListener(polygon, 'rightclick', function (mev) {
      if (mev.vertex != null) {
        polygon.getPath().removeAt(mev.vertex);
      }
    });

    google.maps.event.addListener(polygonPath, 'insert_at', function () {
      updatePolygon();
    });

    google.maps.event.addListener(polygonPath, 'remove_at', function () {
      updatePolygon();
    });

    google.maps.event.addListener(polygonPath, 'set_at', function () {
      updatePolygon();
    });

    google.maps.event.addListener(polygon, 'click', function (event) {

      var infowindowPolygon =
        '<div id="infowindow_polygon">' +
        '<div class="two-boxes"' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '" /></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '" /></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '" /></p>' +
        '</div>' +
        '<div class="two-boxes"' +
        '<p><label for="fill_color">' + fill_color_msg + '</label><input type="text" id="fill_color" size="10" value="' + this.fillColor + '" /></p>' +
        '<p><label for="fill_opacity">' + fill_opacity_msg + '</label><input type="text" id="fill_opacity" size="10" value="' + this.fillOpacity + '" /></p>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowPolygon);
      infowindow.open(map);

    });

    // Rectangle listeners

    google.maps.event.addListener(rectangle, 'bounds_changed', function (event) {
      updateRectangle();
    });

    google.maps.event.addListener(rectangle, 'dragend', function (event) {
      updateRectangle();
    });

    google.maps.event.addListener(rectangle, 'click', function (event) {
      var infowindowRectangle =
        '<div id="infowindow_rectangle">' +
        '<div class="two-boxes"' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '" /></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '" /></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '" /></p>' +
        '</div>' +
        '<div class="two-boxes"' +
        '<p><label for="fill_color">' + fill_color_msg + '</label><input type="text" id="fill_color" size="10" value="' + this.fillColor + '" /></p>' +
        '<p><label for="fill_opacity">' + fill_opacity_msg + '</label><input type="text" id="fill_opacity" size="10" value="' + this.fillOpacity + '" /></p>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowRectangle);
      infowindow.open(map);
    });

    //Circle listeners

    google.maps.event.addListener(circle, 'center_changed', function (event) {
      updateCircle();
    });

    google.maps.event.addListener(circle, 'radius_changed', function (event) {
      updateCircle();
    });

    google.maps.event.addListener(circle, 'click', function (event) {
      var infowindowCircle =
        '<div id="infowindow_circle">' +
        '<div class="two-boxes"' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + this.strokeColor + '" /></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + this.strokeOpacity + '" /></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + this.strokeWeight + '" /></p>' +
        '</div>' +
        '<div class="two-boxes"' +
        '<p><label for="fill_color">' + fill_color_msg + '</label><input type="text" id="fill_color" size="10" value="' + this.fillColor + '" /></p>' +
        '<p><label for="fill_opacity">' + fill_opacity_msg + '</label><input type="text" id="fill_opacity" size="10" value="' + this.fillOpacity + '" /></p>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowCircle);
      infowindow.open(map);
    });

    //Kml listener

    google.maps.event.addListener(kmlLayer, 'click', function (event) {
      var myKmls = [];
      if ($("#kmls_list").val() != '') {
        var kmls_base_url = $("#kmls_base_url").val();
        var kmls_list = $("#kmls_list").val();
        var kmls_array = kmls_list.split(',');
        for (i in kmls_array) {
          this_kml = '<li>' + kmls_array[i] + '</li>';
          myKmls.push(this_kml);
        }
      }

      var custom_kmls = myKmls.join();
      custom_kmls = '<ul>' + custom_kmls.replace(/\,/g, '') + '</ul>';

      if (myKmls != '') {
        var has_custom_kmls = '<h4>' + custom_kmls_msg + '</h4>' +
          '<div style="max-height: 100px;overflow: auto">' +
          custom_kmls +
          '</div>' +
          '<hr />';
      } else {
        var has_custom_kmls = '';
      }

      var infowindowKml =
        '<div id="infowindow_kml" style="cursor: pointer">' +
        has_custom_kmls +
        '<h4>' + kml_url_msg + '</h4>' +
        '<p><input type="text" id="kml_url" size="80" value="' + $('#post_excerpt').val() + '" /></p>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowKml);
      infowindow.open(map);
    });

    // GeoRSS listener

    google.maps.event.addListener(geoRssLayer, 'click', function (event) {

      var infowindowgeoRss =
        '<div id="infowindow_georss" style="cursor: pointer">' +
        '<h4>' + geoRss_url_msg + '</h4>' +
        '<p><input type="text" id="geoRss_url" size="80" value="' + $('#post_excerpt').val() + '" /></p>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowgeoRss);
      infowindow.open(map);
    });

    // directions listener

    google.maps.event.addListener(routePolyline, 'click', function (event) {

      var parts = element_values.split("|");

      var start = parts[0];
      var end = parts[1];
      var weight = parts[2];
      var opacity = parts[3];
      var color = parts[4];
      var show = parts[5];

      if (show == 'true') {
        var state = 'checked = "checked"';
      } else {
        var state = '';
      }

      var infowindowDirections =
        '<div id="infowindow_directions" style="cursor: pointer">' +
        '<div class="two-cols clearfix">' +
        '<div class="col70">' +
        '<p><label for="directions_start">' + directions_start_msg + '</label><input type="text" id="directions_start" size="40" value="' + start + '" /></p>' +
        '<p><label for="directions_end">' + directions_end_msg + '</label><input type="text" id="directions_end" size="40" value="' + end + '" /></p>' +
        '<p><label for="directions_show"><input type="checkbox" id="directions_show" ' + state + ' />' + directions_show_msg + '</label></p>' +
        '</div>' +
        '<div class="col30">' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + color + '" />' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + opacity + '" />' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + weight + '" />' +
        '</div>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK" />' +
        '</div>';

      infowindow.setPosition(event.latLng);
      infowindow.setContent(infowindowDirections);
      infowindow.open(map);

      var input1 = document.getElementById('directions_start');
      var autocomplete = new google.maps.places.Autocomplete(input1);

      var input2 = document.getElementById('directions_end');
      var autocomplete = new google.maps.places.Autocomplete(input2);

    });

    // INFOWINDOW SETTINGS AND ACTIONS

    var infowindow = new google.maps.InfoWindow({});

    // Icons infowindow

    var myIcons = [];
    if ($("#icons_list").val() != '') {
      var icons_base_url = $("#icons_base_url").val();
      var icons_list = $("#icons_list").val();
      var icons_array = icons_list.split(',');
      for (i in icons_array) {
        var this_icon = '<img src="' + icons_base_url + '' + icons_array[i] + '" alt="' + icons_array[i] + '" />';
        myIcons.push(this_icon);
      }
    }

    var custom_icons = myIcons.join();
    custom_icons = custom_icons.replace(/\,/g, '');

    var default_icons_url = $("#plugin_QmarkURL").val();

    if (custom_icons != '') {
      var has_custom_icons = '<h4>' + custom_icons_msg + '</h4>' +
        '<div id="custom_icons_list">' +
        custom_icons +
        '</div>' +
        '<hr />';
    } else {
      var has_custom_icons = '';
    }

    var infowindowIcons =
      '<div id="infowindow_icons" style="cursor: pointer">' +
      has_custom_icons +
      '<h4>' + default_icons_msg + '</h4>' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-blue.png" alt="marker-blue.png"  />' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-green.png" alt="marker-green.png"  />' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-grey.png" alt="marker-grey.png"  />' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-orange.png" alt="marker-orange.png"  />' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-purple.png" alt="marker-purple.png"  />' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-white.png" alt="marker-white.png"  />' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker-yellow.png" alt="marker-yellow.png"  />' +
      '<img src="' + default_icons_url + 'pf=myGmaps/icons/marker.png" alt="marker.png"  />&nbsp;' +
      '</div>';

    // Infowindows actions

    $(document).on('click', '#infowindow_icons img', function () {
      var element_values = $('#post_excerpt').val();
      var parts = element_values.split("|");
      var lat = parseFloat(parts[0]);
      var lng = parseFloat(parts[1]);
      marker.setIcon($(this).attr("src"));
      var icon = $(this).attr("src");
      element_values = marker.position.lat() + "|" + marker.position.lng() + "|" + icon;
      $('#post_excerpt').val(element_values);
      infowindow.close();
    });

    $(document).on('click', '#infowindow_polyline #save', function () {
      var color = $('#stroke_color').val();
      var opacity = $('#stroke_opacity').val();
      var weight = $('#stroke_weight').val();
      polyline.setOptions({
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight
      });

      updatePolyline();

      infowindow.close();
    });

    $(document).on('click', '#infowindow_polygon #save', function () {
      var color = $('#stroke_color').val();
      var opacity = $('#stroke_opacity').val();
      var weight = $('#stroke_weight').val();
      var fill_color = $('#fill_color').val();
      var fill_opacity = $('#fill_opacity').val();
      polygon.setOptions({
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight,
        fillColor: fill_color,
        fillOpacity: fill_opacity
      });

      updatePolygon();

      infowindow.close();
    });

    $(document).on('click', '#infowindow_rectangle #save', function () {
      var weight = $('#stroke_weight').val();
      var opacity = $('#stroke_opacity').val();
      var color = $('#stroke_color').val();
      var fill_color = $('#fill_color').val();
      var fill_opacity = $('#fill_opacity').val();

      rectangle.setOptions({
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight,
        fillColor: fill_color,
        fillOpacity: fill_opacity
      });

      updateRectangle();

      infowindow.close();
    });

	$(document).on('click', '#infowindow_circle #save', function () {
      var weight = $('#stroke_weight').val();
      var opacity = $('#stroke_opacity').val();
      var color = $('#stroke_color').val();
      var fill_color = $('#fill_color').val();
      var fill_opacity = $('#fill_opacity').val();

      circle.setOptions({
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight,
        fillColor: fill_color,
        fillOpacity: fill_opacity
      });

      updateCircle();

      infowindow.close();
    });

	$(document).on('click', '#infowindow_kml li', function () {
      var li_clicked_url = $("#kmls_base_url").val() + $(this).text();

      $('#kml_url').val(li_clicked_url).focus();
    });

	$(document).on('click', '#infowindow_kml #save', function () {
      kmlLayer.setMap(null);
      var url = $('#kml_url').val();
      if (url != null && url != '' && is_url(url)) {
        kmlLayer.setOptions({
          url: url,
          preserveViewport: true,
          suppressInfoWindows: true
        });

        // Save values and type

        $('#element_type').val('included kml file');
        $('#post_excerpt').val(url);
      }

      kmlLayer.setMap(map);
      infowindow.close();
    });

	$(document).on('click', '#infowindow_georss #save', function () {
      geoRssLayer.setMap(null);
      var url = $('#geoRss_url').val();
      if (url != null && url != '' && is_url(url)) {
        geoRssLayer.setOptions({
          url: url,
          preserveViewport: true,
          suppressInfoWindows: true
        });

        // Save values and type

        $('#element_type').val('GeoRSS feed');
        $('#post_excerpt').val(url);
      }

      geoRssLayer.setMap(map);
      infowindow.close();
    });

	$(document).on('click', '#infowindow_directions #save', function () {
      var start = $('#directions_start').val();
      var end = $('#directions_end').val();
      var show = $('#directions_show').prop('checked');
      var color = $('#stroke_color').val();
      var opacity = $('#stroke_opacity').val();
      var weight = $('#stroke_weight').val();

      var polylineRendererOptions = {
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight
      }

      var rendererOptions = {
        polylineOptions: polylineRendererOptions
      }

      var request = {
        origin: start,
        destination: end,
        travelMode: google.maps.TravelMode.DRIVING
      };

      directionsService.route(request, function (result, status) {
        if (status == google.maps.DirectionsStatus.OK) {
          var routePath = result.routes[0].overview_path;
          routePolyline.setPath(routePath);
          directionsDisplay.setOptions({
            options: rendererOptions
          });
          directionsDisplay.setDirections(result);
          directionsDisplay.setMap(map);
        }
      });

      // Save values and type

      element_values = start + "|" + end + "|" + weight +
        "|" + opacity + "|" + color + "|" + show;

      $('#element_type').val('directions');
      $('#post_excerpt').val(element_values);

      directionsDisplay.setMap(map);
      routePolyline.setMap(map);
      infowindow.close();
    });

    // PLACE EXISTING ELEMENTS

    var element_values = $('#post_excerpt').val();
    var element_type = $('input[name=element_type]').val();

    // Place existing marker if any

    if (element_type == 'point of interest') {
      var parts = element_values.split("|");
      var lat = parseFloat(parts[0]);
      var lng = parseFloat(parts[1]);
      var icon = parts[2];
      var location = new google.maps.LatLng(lat, lng);
      marker = new google.maps.Marker({
        position: location,
        draggable: true,
        icon: icon,
        map: map
      });
      markersArray.push(marker);
      $('#add_marker').addClass("active");
      infowindow.setContent(infowindowIcons);

      // Listeners

      google.maps.event.addListener(marker, 'click', function () {
        infowindow.open(map, this);
      });

      google.maps.event.addListener(marker, "dragend", function () {
        element_values = marker.position.lat() + "|" + marker.position.lng() + "|" + icon
        $('#post_excerpt').val(element_values);
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

      var polyline_options = lines.pop();
      var parts = polyline_options.split("|");
      var weight = parseFloat(parts[0]);
      var opacity = parseFloat(parts[1]);
      var color = parts[2];

      polyline.setOptions({
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight
      });

      polylinePath = polyline.getPath();

      polyline.setMap(map);
      $('#add_polyline').addClass("active");

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

      var polygon_options = lines.pop();
      var parts = polygon_options.split("|");
      var weight = parseFloat(parts[0]);
      var opacity = parseFloat(parts[1]);
      var color = parts[2];
      var fill_color = parts[3];
      var fill_opacity = parseFloat(parts[4]);

      polygon.setOptions({
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight,
        fillColor: fill_color,
        fillOpacity: fill_opacity
      });

      polygonPath = polygon.getPath();

      polygon.setMap(map);
      $('#add_polygon').addClass("active");

      // Place existing rectangle if any

    } else if (element_type == 'rectangle') {

      var lines = element_values.split("\n");

      var parts = lines[0].split("|");
      var swlat = parseFloat(parts[0]);
      var nelng = parseFloat(parts[1]);
      var nelat = parseFloat(parts[2]);
      var swlng = parseFloat(parts[3]);
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
        strokeOpacity: opacity,
        strokeWeight: weight,
        fillColor: fill_color,
        fillOpacity: fill_opacity
      });

      $('#add_rectangle').addClass("active");
      rectangle.setMap(map);

      // Place existing circle if any

    } else if (element_type == 'circle') {

      var lines = element_values.split("\n");

      var parts = lines[0].split("|");
      var lat = parseFloat(parts[0]);
      var lng = parseFloat(parts[1]);
      var radius = parseFloat(parts[2]);
      var location = new google.maps.LatLng(lat, lng);

      var parts2 = lines[1].split("|");
      var weight = parseFloat(parts2[0]);
      var opacity = parseFloat(parts2[1]);
      var color = parts2[2];
      var fill_color = parts2[3];
      var fill_opacity = parseFloat(parts2[4]);

      circle.setOptions({
        strokeColor: color,
        strokeOpacity: opacity,
        strokeWeight: weight,
        fillColor: fill_color,
        fillOpacity: fill_opacity,
        center: location,
        radius: radius
      });

      $('#add_circle').addClass("active");
      circle.setMap(map);

      // Place existing kml if any

    } else if (element_type == 'included kml file') {

      kmlLayer.setOptions({
        url: element_values,
        preserveViewport: true,
        suppressInfoWindows: true
      });

      $('#add_kml').addClass("active");
      kmlLayer.setMap(map);

      // Place existing geoRSS if any

    } else if (element_type == 'GeoRSS feed') {

      geoRssLayer.setOptions({
        url: element_values,
        preserveViewport: true,
        suppressInfoWindows: true
      });

      $('#add_georss').addClass("active");
      geoRssLayer.setMap(map);

      // Place existing directions if any

    } else if (element_type == 'directions') {

      var parts = element_values.split("|");

      var start = parts[0];
      var end = parts[1];
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

      var request = {
        origin: start,
        destination: end,
        travelMode: google.maps.TravelMode.DRIVING
      };

      directionsService.route(request, function (result, status) {
        if (status == google.maps.DirectionsStatus.OK) {
          var routePath = result.routes[0].overview_path;
          routePolyline.setPath(routePath);
          directionsDisplay.setOptions({
            options: rendererOptions
          });
          directionsDisplay.setDirections(result);
          directionsDisplay.setMap(map);

        }
      });

      $('#add_directions').addClass("active");
      routePolyline.setMap(map);

    }

    $(".map_toolbar button").each(function () {
      if (!$(this).hasClass("active")) {
        $(this).addClass("inactive");
      }
    });

    // ADD NEW OBJECT OR VERTEX POINT

    // Add marker

    function addMarker(location) {

      // Initialize

      marker = new google.maps.Marker({
        position: location,
        icon: default_icons_url + 'pf=myGmaps/icons/marker.png',
        draggable: true,
        map: map
      });
      markersArray.push(marker);

      infowindow.setContent(infowindowIcons);

      // Listeners

      google.maps.event.addListener(marker, 'click', function () {
        infowindow.open(map, this);
      });

      google.maps.event.addListener(marker, "dragend", function () {
        element_values = marker.position.lat() + "|" + marker.position.lng() + "|" + marker.icon;
        $('#post_excerpt').val(element_values);
      });

      // Save values

      element_values = marker.position.lat() + "|" + marker.position.lng() + "|" + marker.icon;

      $('#element_type').val('point of interest');
      $('#post_excerpt').val(element_values);

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
      var scale = Math.pow(2, map.getZoom());

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

      var scale = Math.pow(2, (Math.PI * 10) - map.getZoom());
      var radius = scale / 500;
      circle.setOptions({
        radius: radius,
        center: location
      });
      circle.setMap(map);

      // Save values

      updateCircle();

    }

    // Add kml

    function addKml(location) {
      var myKmls = [];
      if ($("#kmls_list").val() != '') {
        var kmls_base_url = $("#kmls_base_url").val();
        var kmls_list = $("#kmls_list").val();
        var kmls_array = kmls_list.split(',');
        for (i in kmls_array) {
          this_kml = '<li>' + kmls_array[i] + '</li>';
          myKmls.push(this_kml);
        }
      }

      var custom_kmls = myKmls.join();
      custom_kmls = '<ul>' + custom_kmls.replace(/\,/g, '') + '</ul>';

      if (myKmls != '') {
        var has_custom_kmls = '<h4>' + custom_kmls_msg + '</h4>' +
          '<div style="max-height: 100px;overflow: auto">' +
          custom_kmls +
          '</div>' +
          '<hr />';
      } else {
        var has_custom_kmls = '';
      }

      var infowindowKml =
        '<div id="infowindow_kml" style="cursor: pointer">' +
        has_custom_kmls +
        '<h4>' + kml_url_msg + '</h4>' +
        '<p><input type="text" id="kml_url" size="80" value="' + $('#post_excerpt').val() + '" /></p>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(location);
      infowindow.setContent(infowindowKml);
      infowindow.open(map);
    }

    // Add geoRSS

    function addgeoRSS(location) {

      var infowindowgeoRss =
        '<div id="infowindow_georss" style="cursor: pointer">' +
        '<h4>' + geoRss_url_msg + '</h4>' +
        '<p><input type="text" id="geoRss_url" size="80" value="' + $('#post_excerpt').val() + '" /></p>' +
        '<p><input type="button" id="save" value="OK" /></p>' +
        '</div>';
      infowindow.setPosition(location);
      infowindow.setContent(infowindowgeoRss);
      infowindow.open(map);
    }

    // Add directions

    function addDirections(location) {

      var color = '#555';
      var opacity = 0.8;
      var weight = 3;

      var infowindowDirections =
        '<div id="infowindow_directions" style="cursor: pointer">' +
        '<div class="two-cols clearfix">' +
        '<div class="col70">' +
        '<p><label for="directions_start">' + directions_start_msg + '</label><input type="text" id="directions_start" size="40" value="" /></p>' +
        '<p><label for="directions_end">' + directions_end_msg + '</label><input type="text" id="directions_end" size="40" value="" /></p>' +
        '<p><label for="directions_show"><input type="checkbox" id="directions_show" />' + directions_show_msg + '</label></p>' +
        '</div>' +
        '<div class="col30">' +
        '<p><label for="stroke_color">' + stroke_color_msg + '</label><input type="text" id="stroke_color" size="10" class="colorpicker" value="' + color + '" /></p>' +
        '<p><label for="stroke_opacity">' + stroke_opacity_msg + '</label><input type="text" id="stroke_opacity" size="10" value="' + opacity + '" /></p>' +
        '<p><label for="stroke_weight">' + stroke_weight_msg + '</label><input type="text" id="stroke_weight" size="10" value="' + weight + '" /></p>' +
        '</div>' +
        '</div>' +
        '<p><input type="button" id="save" value="OK" />' +
        '</div>';

      infowindow.setPosition(location);
      infowindow.setContent(infowindowDirections);
      infowindow.open(map);

      var input1 = document.getElementById('directions_start');
      var autocomplete = new google.maps.places.Autocomplete(input1);

      var input2 = document.getElementById('directions_end');
      var autocomplete = new google.maps.places.Autocomplete(input2);
    }


    // DELETE EXISTING ELEMENT

    function deleteMap() {

      $(".map_toolbar button").each(function () {
        $(this).removeClass("active");
        $(this).removeClass("inactive");
      });
      $("#delete_map").blur();

      // Save default values

      for (i in markersArray) {
        markersArray[i].setMap(null);
      }

      markersArray.length = 0;
      vertexArray.length = 0;

      polyline.setOptions({
        strokeColor: '#555',
        strokeOpacity: 0.8,
        strokeWeight: 3
      });
      polylinePath.clear();
      polyline.setMap(null);

      polygon.setOptions({
        strokeColor: '#555',
        strokeOpacity: 0.8,
        strokeWeight: 3,
        fillColor: '#ccc',
        fillOpacity: 0.35
      });
      polygonPath.clear();
      polygon.setMap(null);

      rectangle.setOptions({
        strokeColor: '#555',
        strokeOpacity: 0.8,
        strokeWeight: 3,
        fillColor: '#ccc',
        fillOpacity: 0.35,
        radius: 100
      });
      rectangle.setMap(null);

      circle.setOptions({
        strokeColor: '#555',
        strokeOpacity: 0.8,
        strokeWeight: 3,
        fillColor: '#ccc',
        fillOpacity: 0.35,
        radius: 100
      });
      circle.setMap(null);

      kmlLayer.setOptions({});
      kmlLayer.setMap(null);

      geoRssLayer.setOptions({});
      geoRssLayer.setMap(null);

      routePolylinePath.clear();
      routePolyline.setMap(null);
      directionsDisplay.setMap(null);

      $('#element_type').val('none');
      $('#post_excerpt').val('');

    }

    // SUBMIT FORM AND SAVE ELEMENT

    $('#entry-form').submit(function () {
      var element_type = $('#element_type').val();
      if (element_type == '') {
        $('#element_type').val('none');
      }

      var default_location = map.getCenter().lat() + ',' + map.getCenter().lng();
      var default_zoom = map.getZoom();
      var default_type = map.getMapTypeId();

      $('input[name=myGmaps_center]').attr('value', default_location);
      $('input[name=myGmaps_zoom]').attr('value', default_zoom);
      $('input[name=myGmaps_type]').attr('value', default_type);
      return true;

    });
  }
});
