const map = L.map('map-container');
const tiles = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 20,
    attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, ' +
        'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
    id: 'mapbox/streets-v11',
    tileSize: 512,
    zoomOffset: -1
}).addTo(map);

/*  
NOTE: If a more detailed satellite tile layer is desired, mapbox is an option but may incur charges. See tile layer URL here 'https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=YOUR_API_KEY'
*/

const coords = drupalSettings.bos_assessing.coords[0];
const polyCoords = [];

jQuery(coords).each(function(index,value) {
    polyCoords.push( {"lng" : value[0], "lat" : value[1]} );
});


const latlngs = [[42.3815461084932, -71.0652163357137,],[42.381464191324, -71.0652765658405],[42.3815438410899,-71.065470469088],[42.3816263700611,-71.0654117771252],[42.3815461084932,-71.0652163357137]];
const polygon = L.polygon(polyCoords, {color: 'red'}).addTo(map);

// zoom the map to the polygon
map.fitBounds(polygon.getBounds()).setZoom(18);
