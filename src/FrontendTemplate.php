<?php
/**
 * @brief myGmaps, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Philippe aka amalgame and contributors
 *
 * @copyright GPL-2.0 [https://www.gnu.org/licenses/gpl-2.0.html]
 */

declare(strict_types=1);

namespace Dotclear\Plugin\myGmaps;

use dcCore;

class FrontendTemplate
{
    public static function publicHtmlContent(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['id', 'mapContainerStyles', 'mapCanvasStyles']
        );

        $sId                 = $aOptions['id'];
        $sMapContainerStyles = $aOptions['mapContainerStyles'];
        $sMapCanvasStyles    = $aOptions['mapCanvasStyles'];

        $sNoScriptMessage = __('Sorry, javascript must be activated in your browser to see this map.');
        $sOutput          = <<<EOT
            <div id="map_box_{$sId}" style="{$sMapContainerStyles}">
                <div id="map_canvas_{$sId}" class="map_canvas" style="{$sMapCanvasStyles}">
                    <noscript><p>{$sNoScriptMessage}</p></noscript>
                </div>
                <div id="panel_{$sId}" class="panel"></div>
            </div>\n
            EOT;

        return $sOutput;
    }

    public static function publicCssContent(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['public_path']
        );
        $sPublicPath = $aOptions['public_path'];

        return '<link rel="stylesheet" type="text/css" href="' . $sPublicPath . '/css/public.css" />' . "\n";
    }

    public static function publicJsContent(array $aOptions)
    {
        $s = dcCore::app()->blog->settings->myGmaps;

        return '<script src="https://maps.googleapis.com/maps/api/js?key=' . $s->myGmaps_API_key . '&amp;callback=Function.prototype"></script>' . "\n";
    }

    public static function getMapOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['elements', 'style', 'styles_path', 'zoom', 'center', 'map_id', 'has_marker', 'has_poly']
        );
        $sElements   = $aOptions['elements'];
        $sStyle      = $aOptions['style'];
        $sStylesPath = $aOptions['styles_path'];
        $sZoom       = $aOptions['zoom'];
        $sCenter     = $aOptions['center'];
        $sMapId      = $aOptions['map_id'];
        $bHasMarker  = $aOptions['has_marker'];
        $bHasPoly    = $aOptions['has_poly'];

        $sOutput = <<<EOT
            <script>
            //<![CDATA[
            $(function () {
            EOT;

        // Set map styles
        $sOutput .= self::getMapStyles([
            'style'       => $sStyle,
            'styles_path' => $sStylesPath,
            'zoom'        => $sZoom,
            'center'      => $sCenter,
            'map_id'      => $sMapId,
        ]);

        // Set map events
        $sOutput .= self::getMapEvents([
            'map_id'     => $sMapId,
            'has_marker' => $bHasMarker,
            'has_poly'   => $bHasPoly,
        ]);

        // Map elements
        $sOutput .= $sElements;

        $sOutput .= <<<EOT
            });
            //]]>
            </script>\n
            EOT;

        return $sOutput;
    }

    protected static function getMapStyles(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['style', 'styles_path', 'zoom', 'center', 'map_id']
        );
        $sStyle      = $aOptions['style'];
        $sStylesPath = $aOptions['styles_path'];
        $sZoom       = $aOptions['zoom'];
        $sCenter     = $aOptions['center'];
        $sMapId      = $aOptions['map_id'];
        $sOutput     = '';

        $sNeutralBlueStyle = <<<EOT
            var neutral_blue_styles = [{"featureType":"water","elementType":"geometry","stylers":[{"color":"#193341"}]},{"featureType":"landscape","elementType":"geometry","stylers":[{"color":"#2c5a71"}]},{"featureType":"road","elementType":"geometry","stylers":[{"color":"#29768a"},{"lightness":-37}]},{"featureType":"poi","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"featureType":"transit","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"elementType":"labels.text.stroke","stylers":[{"visibility":"on"},{"color":"#3e606f"},{"weight":2},{"gamma":0.84}]},{"elementType":"labels.text.fill","stylers":[{"color":"#ffffff"}]},{"featureType":"administrative","elementType":"geometry","stylers":[{"weight":0.6},{"color":"#1a3541"}]},{"elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"color":"#2c5a71"}]}];
            var neutral_blue = new google.maps.StyledMapType(neutral_blue_styles,{name: "Neutral Blue"});\n
            EOT;

        $custom_style = false;
        if ($sStyle == 'roadmap') {
            $sStyle = 'google.maps.MapTypeId.ROADMAP';
        } elseif ($sStyle == 'satellite') {
            $sStyle = 'google.maps.MapTypeId.SATELLITE';
        } elseif ($sStyle == 'hybrid') {
            $sStyle = 'google.maps.MapTypeId.HYBRID';
        } elseif ($sStyle == 'terrain') {
            $sStyle = 'google.maps.MapTypeId.TERRAIN';
        } elseif ($sStyle == 'OpenStreetMap') {
            $sStyle = 'OpenStreetMap';
        } else {
            $custom_style = true;
        }

        // Create map and listener
        if ($sStyle == 'neutral_blue') {
            $sOutput .= $sNeutralBlueStyle;
        } elseif ($sStyle != 'neutral_blue' && $custom_style) {
            if (is_dir($sStylesPath)) {
                $sStyleId         = $sStyle . '_styles';
                $sStyleDefinition = file_get_contents($sStylesPath . '/' . $sStyleId . '.js');
                $sStyleName       = preg_replace('/_styles/s', '', $sStyleId);
                $sStyleNiceName   = ucwords(preg_replace('/_/s', ' ', $sStyleName));

                $sOutput .= <<<EOT
                    var {$sStyleId} = {$sStyleDefinition};
                    var {$sStyleName} = new google.maps.StyledMapType({$sStyleId},{name: "{$sStyleNiceName}"});\n
                    EOT;
            }
        }

        $sOutput .= <<<EOT
            var myOptions = {
                zoom: parseFloat({$sZoom}),
                center: new google.maps.LatLng({$sCenter}),
                scrollwheel: false,
                mapTypeControl: false,
                mapTypeControlOptions: {
                    mapTypeIds: ["{$sStyle}"]
                }
            };
            var map_{$sMapId} = new google.maps.Map(document.getElementById("map_canvas_{$sMapId}"), myOptions);\n
            EOT;

        if ($custom_style) {
            $sOutput .= <<<EOT
                map_{$sMapId}.mapTypes.set("{$aOptions['style']}", {$aOptions['style']});
                map_{$sMapId}.setMapTypeId("{$aOptions['style']}");\n
                EOT;
        } elseif ($custom_style == false && $sStyle == 'OpenStreetMap') {
            $sOutput .= <<<EOT
                var credit = '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap Contributors</a>';
                var creditNode = document.createElement('div');
                creditNode.id = 'credit-control';
                creditNode.index = 1;
                map_{$sMapId}.controls[google.maps.ControlPosition.BOTTOM_RIGHT].push(creditNode);
                creditNode.innerHTML = credit;
                map_{$sMapId}.mapTypes.set("OpenStreetMap", new google.maps.ImageMapType({
                    getTileUrl: function(coord, zoom) {
                        return "https://tile.openstreetmap.org/" + zoom + "/" + coord.x + "/" + coord.y + ".png";
                    },
                    tileSize: new google.maps.Size(256, 256),
                    name: "OpenStreetMap",
                    maxZoom: 18
                }));
                map_{$sMapId}.setMapTypeId("{$sStyle}");\n
                EOT;
        } else {
            $sOutput .= 'map_' . $sMapId . '.setOptions({mapTypeId: ' . $sStyle . '});' . "\n";
        }

        return $sOutput;
    }

    protected static function getMapEvents(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'has_marker', 'has_poly']
        );
        $sMapId     = $aOptions['map_id'];
        $bHasMarker = $aOptions['has_marker'];
        $bHasPoly   = $aOptions['has_poly'];
        $sOutput    = '';

        if ($bHasMarker) {
            $sOutput .= self::getMarkerInfoWindow(['map_id' => $sMapId]);
        }
        if ($bHasPoly) {
            $sOutput .= self::getPolyInfoWindow(['map_id' => $sMapId]);
        }

        $sOutput .= <<<EOT
            var infowindow_{$sMapId} = new google.maps.InfoWindow({});
            google.maps.event.addListener(map_{$sMapId}, "click", function (event) {
                infowindow_{$sMapId}.close();
            });\n
            EOT;

        return $sOutput;
    }

    public static function getMapElementOptions(array $aElementOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aElementOptions),
            ['map_id', 'element_id', 'title', 'description', 'type']
        );
        $sMapId       = $aElementOptions['map_id'];
        $sId          = $aElementOptions['element_id'];
        $sTitle       = $aElementOptions['title'];
        $sDescription = $aElementOptions['description'];
        $sType        = $aElementOptions['type'];

        $sOutput = <<<EOT
            var title_{$sId} = "{$sTitle}";
            var content_{$sId} = '{$sDescription}';\n
            EOT;

        if ($sType == 'point of interest') {
            $sOutput .= self::getMapElementMarkerOptions($aElementOptions);
        } elseif ($sType == 'polyline') {
            $sOutput .= self::getMapElementPolylineOptions($aElementOptions);
        } elseif ($sType == 'polygon') {
            $sOutput .= self::getMapElementPolygonOptions($aElementOptions);
        } elseif ($sType == 'rectangle') {
            $sOutput .= self::getMapElementRectangleOptions($aElementOptions);
        } elseif ($sType == 'circle') {
            $sOutput .= self::getMapElementCircleOptions($aElementOptions);
        } elseif ($sType == 'included kml file' || $sType == 'GeoRSS feed') {
            $sOutput .= self::getMapElementKmlOptions($aElementOptions);
        } elseif ($sType == 'directions') {
            $sOutput .= self::getMapElementDirectionsOptions($aElementOptions);
        }

        return $sOutput;
    }

    protected static function getMapElementMarkerOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'element_id', 'position', 'icon']
        );
        $sMapId    = $aOptions['map_id'];
        $sId       = $aOptions['element_id'];
        $sPosition = $aOptions['position'];
        $sIcon     = $aOptions['icon'];

        $sOutput = <<<EOT
            marker = new google.maps.Marker({
                icon : "{$sIcon}",
                position: new google.maps.LatLng({$sPosition}),
                title: title_{$sId},
                map: map_{$sMapId}
            });
            google.maps.event.addListener(marker, "click", function() {
                openmarkerinfowindow(this,title_{$sId},content_{$sId});
            });\n
            EOT;

        return $sOutput;
    }

    protected static function getMapElementPolylineOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'element_id', 'coordinates', 'stroke_color', 'stroke_opacity', 'stroke_weight']
        );
        $sMapId         = $aOptions['map_id'];
        $sId            = $aOptions['element_id'];
        $sCoordinates   = $aOptions['coordinates'];
        $sStrokeColor   = $aOptions['stroke_color'];
        $sStrokeOpacity = $aOptions['stroke_opacity'];
        $sStrokeWeight  = $aOptions['stroke_weight'];

        $sPath = '';
        foreach ($sCoordinates as $sPoint) {
            $sPath .= 'new google.maps.LatLng(' . $sPoint . '),';
        }
        $sPath = substr($sPath, 0, -1);

        $sOutput = <<<EOT
            var polyline = new google.maps.Polyline({
                path: [{$sPath}],
                strokeColor: "{$sStrokeColor}",
                strokeOpacity: {$sStrokeOpacity},
                strokeWeight: {$sStrokeWeight}
            });
            polyline.setMap(map_{$sMapId});
            google.maps.event.addListener(polyline, "click", function(event) {
                var pos = event.latLng;
                openpolyinfowindow(title_{$sId},content_{$sId},pos);
            });\n
            EOT;

        return $sOutput;
    }

    protected static function getMapElementPolygonOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'element_id', 'coordinates', 'stroke_color', 'stroke_opacity', 'stroke_weight', 'fill_color', 'fill_opacity']
        );
        $sMapId         = $aOptions['map_id'];
        $sId            = $aOptions['element_id'];
        $sCoordinates   = $aOptions['coordinates'];
        $sStrokeColor   = $aOptions['stroke_color'];
        $sStrokeOpacity = $aOptions['stroke_opacity'];
        $sStrokeWeight  = $aOptions['stroke_weight'];
        $sFillColor     = $aOptions['fill_color'];
        $sFillOpacity   = $aOptions['fill_opacity'];

        $sPath = '';
        foreach ($sCoordinates as $sPoint) {
            $sPath .= 'new google.maps.LatLng(' . $sPoint . '),';
        }
        $sPath = substr($sPath, 0, -1);

        $sOutput = <<<EOT
            var polygon = new google.maps.Polygon({
                path: [{$sPath}],
                strokeColor: "{$sStrokeColor}",
                strokeOpacity: {$sStrokeOpacity},
                strokeWeight: {$sStrokeWeight},
                fillColor: "{$sFillColor}",
                fillOpacity: {$sFillOpacity}
            });
            polygon.setMap(map_{$sMapId});
            google.maps.event.addListener(polygon, "click", function(event) {
                var pos = event.latLng;
                openpolyinfowindow(title_{$sId},content_{$sId},pos);
            });\n
            EOT;

        return $sOutput;
    }

    protected static function getMapElementRectangleOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'element_id', 'bound1', 'bound2', 'stroke_color', 'stroke_opacity', 'stroke_weight', 'fill_color', 'fill_opacity']
        );
        $sMapId         = $aOptions['map_id'];
        $sId            = $aOptions['element_id'];
        $sBound1        = $aOptions['bound1'];
        $sBound2        = $aOptions['bound2'];
        $sStrokeColor   = $aOptions['stroke_color'];
        $sStrokeOpacity = $aOptions['stroke_opacity'];
        $sStrokeWeight  = $aOptions['stroke_weight'];
        $sFillColor     = $aOptions['fill_color'];
        $sFillOpacity   = $aOptions['fill_opacity'];

        $sOutput = <<<EOT
            var bounds = new google.maps.LatLngBounds(
                new google.maps.LatLng({$sBound1}),
                new google.maps.LatLng({$sBound2})
            );

            var rectangle = new google.maps.Rectangle({
                strokeColor: "{$sStrokeColor}",
                strokeOpacity: {$sStrokeOpacity},
                strokeWeight: {$sStrokeWeight},
                fillColor: "{$sFillColor}",
                fillOpacity: {$sFillOpacity}
            });
            rectangle.setBounds(bounds);
            rectangle.setMap(map_{$sMapId});
            google.maps.event.addListener(rectangle, "click", function(event) {
                var pos = event.latLng;
                openpolyinfowindow(title_{$sId},content_{$sId},pos);
            });\n
            EOT;

        return $sOutput;
    }

    protected static function getMapElementCircleOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'element_id', 'center', 'radius', 'stroke_color', 'stroke_opacity', 'stroke_weight', 'fill_color', 'fill_opacity']
        );
        $sMapId         = $aOptions['map_id'];
        $sId            = $aOptions['element_id'];
        $sCenter        = $aOptions['center'];
        $sRadius        = $aOptions['radius'];
        $sStrokeColor   = $aOptions['stroke_color'];
        $sStrokeOpacity = $aOptions['stroke_opacity'];
        $sStrokeWeight  = $aOptions['stroke_weight'];
        $sFillColor     = $aOptions['fill_color'];
        $sFillOpacity   = $aOptions['fill_opacity'];

        $sOutput = <<<EOT
            var circle = new google.maps.Circle({
                center: new google.maps.LatLng({$sCenter}),
                radius: {$sRadius},
                strokeColor: "{$sStrokeColor}",
                strokeOpacity: {$sStrokeOpacity},
                strokeWeight: {$sStrokeWeight},
                fillColor: "{$sFillColor}",
                fillOpacity: {$sFillOpacity}
            });
            circle.setMap(map_{$sMapId});
            google.maps.event.addListener(circle, "click", function(event) {
                var pos = event.latLng;
                openpolyinfowindow(title_{$sId},content_{$sId},pos);
            });\n
            EOT;

        return $sOutput;
    }

    protected static function getMapElementKmlOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'layer']
        );
        $sMapId = $aOptions['map_id'];
        $sLayer = $aOptions['layer'];

        $sOutput = <<<EOT
            var layer = new google.maps.KmlLayer("{$sLayer}", {preserveViewport: true});
            layer.setMap(map_{$sMapId});\n
            EOT;

        return $sOutput;
    }

    protected static function getMapElementDirectionsOptions(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id', 'element_id', 'stroke_color', 'stroke_opacity', 'stroke_weight', 'origin', 'destination', 'display_direction']
        );
        $sMapId            = $aOptions['map_id'];
        $sId               = $aOptions['element_id'];
        $sStrokeColor      = $aOptions['stroke_color'];
        $sStrokeOpacity    = $aOptions['stroke_opacity'];
        $sStrokeWeight     = $aOptions['stroke_weight'];
        $sOrigin           = $aOptions['origin'];
        $sDestination      = $aOptions['destination'];
        $bDisplayDirection = $aOptions['display_direction'];

        $sOutput = <<<EOT
            var routePolyline;
            var routePolylineOptions = {
                strokeColor: "#555",
                strokeOpacity: 0,
                strokeWeight: 20,
                zIndex: 1
            };
            routePolyline = new google.maps.Polyline(routePolylineOptions);
            var routePolylinePath = routePolyline.getPath();
            var directionsService = new google.maps.DirectionsService();
            var polylineRendererOptions = {
                strokeColor: "{$sStrokeColor}",
                strokeOpacity: parseFloat({$sStrokeOpacity}),
                strokeWeight: parseFloat({$sStrokeWeight})
            }
            var rendererOptions = {
                polylineOptions: polylineRendererOptions
            }
            var directionsDisplay = new google.maps.DirectionsRenderer(rendererOptions);
            var request = {
                origin: "{$sOrigin}",
                destination: "{$sDestination}",
                travelMode: google.maps.TravelMode.DRIVING
            };
            EOT;
        if ($bDisplayDirection == 'true') {
            $sOutput .= '$("#map_box_' . $sMapId . '").addClass( "directions" );' . "\n";
        } else {
            $sOutput .= '$("#map_box_' . $sMapId . '").addClass( "no-directions" );' . "\n";
        }

        $sOutput .= <<<EOT
            directionsService.route(request, function(result, status) {
                if (status == google.maps.DirectionsStatus.OK) {
                    var routePath = result.routes[0].overview_path;
                    routePolyline.setPath(routePath);
                    directionsDisplay.setPanel(document.getElementById("panel_{$sMapId}"));
                    directionsDisplay.setOptions({options: rendererOptions});
                    directionsDisplay.setDirections(result);
                    directionsDisplay.setMap(map_{$sMapId});
                    routePolyline.setMap(map_{$sMapId});
                } else {
                    alert(status);
                }
            });
            google.maps.event.addListener(routePolyline, "click", function(event) {
                var pos = event.latLng;
                openpolyinfowindow(title_{$sId},content_{$sId},pos);
            });\n
            EOT;

        return $sOutput;
    }

    protected static function getMarkerInfoWindow(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id']
        );
        $sMapId = $aOptions['map_id'];

        $sOutput = <<<EOT
            function openmarkerinfowindow(marker,title,content) {
                infowindow_{$sMapId}.setContent(
                    "<h3>"+title+"</h3>"+
                    "<div class=\"post-infowindow\" id=\"post-infowindow_{$sMapId}\">"+content+"</div>"
                );
                infowindow_{$sMapId}.open(map_{$sMapId}, marker);
                $("#post-infowindow_{$sMapId}").parent("div", "div#map_canvas_{$sMapId}").css("overflow","hidden");
            }\n
            EOT;

        return $sOutput;
    }

    protected static function getPolyInfoWindow(array $aOptions)
    {
        self::checkOptions(
            get_called_class(),
            array_keys($aOptions),
            ['map_id']
        );
        $sMapId = $aOptions['map_id'];

        $sOutput = <<<EOT
            function openpolyinfowindow(title,content, pos) {
                infowindow_{$sMapId}.setPosition(pos);
                infowindow_{$sMapId}.setContent(
                    "<h3>"+title+"</h3>"+
                    "<div class=\"post-infowindow\" id=\"post-infowindow_{$sMapId}\">"+content+"</div>"
                );
                infowindow_{$sMapId}.open(map_{$sMapId});
                $("#post-infowindow_{$sMapId}").parent("div", "div#map_canvas_{$sMapId}").css("overflow","hidden");
            }\n
            EOT;

        return $sOutput;
    }

    /**
     * Tools to check if required arguments are specified
     * @param string $sMethod Called method
     * @param array $aKeysOptions all keys of options
     * @param array $aRequiredOptions all keys to check
     * @return bool false if an error
     */
    protected static function checkOptions($sMethod, array $aKeysOptions, array $aRequiredOptions)
    {
        $aErrors = [];
        foreach ($aRequiredOptions as $sRequiredOption) {
            if (!in_array($sRequiredOption, $aKeysOptions)) {
                $aErrors[] = $sRequiredOption;
            }
        }
        if (!empty($aErrors)) {
            //throw new Exception('Option ' . implode(', ', $aErrors) . ' missing from method ' . $sMethod);
            var_dump('Options ' . implode(', ', $aErrors) . ' missing when method ' . $sMethod . ' call.');

            return false;
        }

        return true;
    }
}
