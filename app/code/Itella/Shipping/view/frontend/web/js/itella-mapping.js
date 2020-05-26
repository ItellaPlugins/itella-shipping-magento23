class itellaMapping {
  constructor(el) {

    /* Itella Mapping version */
    this.version = '1.2.0';

    this._isDebug = false;

    /* default map center Lithuania Kaunas */
    this._defaultMapPos = [54.890926, 23.919338];

    /* zoom levels for map */
    this.ZOOM_DEFAULT = 8;
    this.ZOOM_SELECTED = 13;
    this.ZOOM_MAX = 18;
    this.ZOOM_MIN = 4;

    /**
     * Element where to mount Itella Mapping
     * @type HTMLElement
     */
    this.mount = el;

    /* Leaflet elements */
    this._map = null;
    this._pickupIcon = null;
    this._markerLayer = null;
    this._latlongArray = [];

    /* Pointers to often used modal elements */
    this.UI = {};
    this.UI.container = null;
    this.UI.modal = null;

    this.images_url = '';

    this.strings = {
      modal_header: 'Pickup points',
      selector_header: 'Pickup point',
      workhours_header: 'Workhours',
      contacts_header: 'Contacts',
      search_placeholder: 'Enter postcode/address',
      select_pickup_point: 'Select a pickup point',
      no_pickup_points: 'No points to select',
      select_btn: 'select',
      back_to_list_btn: 'reset search',
      nothing_found: 'Nothing found',
      select_pickup_point_btn: 'Select pickup point',
      no_information: 'No information',
      error_leaflet: 'Leaflet is required for Itella-Mapping',
      error_missing_mount_el: 'No mount supplied to itellaShipping'
    }

    this.country = 'LT';

    /* Functions to run after point is selected */
    this.callbackList = [];

    /* Selected pickup */
    this.selectedPoint = null;
    this.locations = [];
    this._locations = [];
    this._isSearchResult = false;

    this._searchTimeoutId = null;

    if (typeof L === 'undefined') {
      console.error(this.strings.error_leaflet);
    }

    itellaRegisterLeafletPlugins();
  }

  setImagesUrl(images_url) {
    this.images_url = images_url;
    return this;
  }

  setStrings(strings) {
    this.strings = { ...this.strings, ...strings };
    return this;
  }

  getStrings() {
    return this.strings;
  }

  init() {
    if (typeof this.mount !== 'undefined') {
      this.buildContainer()
        .buildModal()
        .setupLeafletMap(this.UI.modal.getElementsByClassName('itella-map')[0])
        .attachListeners();
      return this;
    }
    return false;
  }

  getVersion() {
    return this.version;
  }

  getMount() {
    return this.mount;
  }

  showEl(el) {
    el.classList.remove('hidden');
  }

  hideEl(el) {
    el.classList.add('hidden');
  }

  buildModal() {
    var template =
      `
    <div class="itella-container">
      <div class="close-modal">
        <img src="${this.images_url}x-symbol.svg" alt="Close map">
      </div>
      <div class="itella-map"></div>
      <div class="itella-card">
        <div class="itella-card-header">
          <h2>${this.strings.modal_header}</h2>
          <img src="${this.images_url}logo_small_white.png" alt="Itella logo">
        </div>
        <div class="itella-card-content">
          <h3>${this.strings.selector_header}</h3>
          <div class="itella-select">
            <div class="dropdown">${this.strings.select_pickup_point}</div>
            <div class="dropdown-inner">
              <div class="search-bar">
                <input type="text" placeholder="${this.strings.search_placeholder}" class="search-input">
                <img src="${this.images_url}search.png" alt="Search">
              </div>
              <span class="search-by"></span>
              <ul>
                <li class="city">${this.strings.no_pickup_points}</li>
              </ul>
            </div>
          </div>

          <div class="point-info">
            <div class="workhours">
              <h4 class="title">${this.strings.workhours_header}</h4>
              <div class="workhours-info">
                <ol>
                </ol>
              </div>
            </div>
            <div class="contacts">
              <h4 class="title">${this.strings.contacts_header}</h4>
              <div class="contacts-info">
                <ul>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="itella-card-footer">
          <button class="itella-btn itella-back hidden">${this.strings.back_to_list_btn}</button>
          <button class="itella-btn itella-submit">${this.strings.select_btn}</button>
        </div>
      </div>
    </div>
    `;
    if (typeof this.mount === 'undefined') {
      console.info(this.strings.error_missing_mount_el);
      return false;
    }
    let modal = this.createElement('div', ['itella-mapping-modal', 'hidden']);
    modal.innerHTML = template;

    /* if exists destroy and rebuild */
    if (this.UI.modal !== null) {
      this.UI.modal.parentNode.removeChild(this.UI.modal);
      this.UI.modal = null;
    }

    this.UI.modal = modal;
    this.UI.container.appendChild(this.UI.modal);
    this.UI.back_to_list_btn = this.UI.container.querySelector('.itella-btn.itella-back');

    return this;
  }

  buildContainer() {
    let template = `
      <div class="itella-chosen-point">${this.strings.select_pickup_point}</div>
      <a href='#' class="itella-modal-btn">${this.strings.select_pickup_point_btn}</a>
    `;
    let container = this.createElement('div', ['itella-shipping-container']);
    container.innerHTML = template;

    this.UI.container = container;
    this.mount.appendChild(this.UI.container);

    return this;
  }

  attachListeners() {
    let _this = this;
    this.UI.container.getElementsByClassName('itella-modal-btn')[0].addEventListener('click', function (e) {
      e.preventDefault();
      _this.showEl(_this.UI.modal);
      _this._map.invalidateSize();
      if (_this.selectedPoint === null) {
        _this.setMapView(_this._defaultMapPos, _this.ZOOM_DEFAULT);
      } else {
        _this.setMapView(_this.selectedPoint.location, _this.ZOOM_SELECTED);
        if (typeof _this.selectedPoint._marker._icon !== 'undefined' && _this.selectedPoint._marker._icon) {
          _this.selectedPoint._marker._icon.classList.add('active');
        }
      }
    });

    this.UI.container.getElementsByClassName('search-input')[0].addEventListener('keyup', function (e) {
      e.preventDefault();
      let force = false;
      /* Enter key forces search to not wait */
      if (e.keyCode == '13') {
        force = true;
      }
      _this.searchNearestDebounce(this.value, force);
    });

    this.UI.container.getElementsByClassName('close-modal')[0].addEventListener('click', function (e) {
      e.preventDefault();
      _this.hideEl(_this.UI.modal);
    });

    this.UI.container.getElementsByClassName('itella-submit')[0].addEventListener('click', function (e) {
      e.preventDefault();
      _this.submitSelection();
    });

    let select = _this.UI.modal.querySelector('.itella-select');
    let drpd = _this.UI.modal.querySelector('.itella-select .dropdown');
    let select_options = select.querySelector('.dropdown-inner');
    drpd.addEventListener('click', function (e) {
      e.preventDefault();
      select.classList.toggle('open');
    });
    this.UI.modal.addEventListener('click', function (e) {
      if (select.classList.contains('open')
        && !(select_options.contains(e.target) || drpd == e.target)
      ) {
        select.classList.remove('open');
      }
      if (_this._isDebug) {
        console.log('CLICKED HTML EL:', e.target.nodeName, e.target.dataset);
      }
      if (e.target.nodeName.toLowerCase() == 'li' && typeof e.target.dataset.id !== 'undefined') {
        let point = _this.getLocationById(e.target.dataset.id);
        if (_this._isDebug) {
          console.log('Selected from dropdown:', point);
        }
        _this.selectedPoint = point;
        _this.renderPointInfo(point);
        select.classList.remove('open');
        _this.setMapView(point.location, _this.ZOOM_SELECTED);
        _this.setActiveMarkerByTerminalId(e.target.dataset.id);
      }
    });

    this._markerLayer.on('clusterclick', function (a) {
      // a.layer is actually a cluster
      a.layer.zoomToBounds();
      //_this.setMapView(_this._map.getCenter(), _this._map.getZoom());
    });

    this._markerLayer.on('click', function (e) {
      _this.removeActiveClass();
      // _this._markerLayer.eachLayer(function (icon) {
      //   if (typeof icon._icon != 'undefined')
      //     L.DomUtil.removeClass(icon._icon, "active");
      // });
      L.DomUtil.addClass(e.layer._icon, "active");
      _this.setMapView(e.layer.getLatLng(), _this._map.getZoom());
      let temp = _this.getLocationById(e.layer.options.pickupPointId);
      _this.renderPointInfo(temp);
      _this.selectedPoint = temp;
      if (_this._isDebug) {
        console.log('Selected pickup point ID:', temp, e.layer.getLatLng());
      }
    });

    this._markerLayer.on('animationend', function (e) {
      if (_this.selectedPoint && _this.selectedPoint._marker._icon) {
        _this.selectedPoint._marker._icon.classList.add('active');
      }
    });

    return this;
  }

  removeActiveClass() {
    if (this.selectedPoint && this.selectedPoint._marker._icon) {
      this.selectedPoint._marker._icon.classList.remove('active');
    }
  }

  resetSearch() {
    this._isSearchResult = false;
    this.locations.forEach(function (loc) {
      loc.distance = undefined;
    });
    this.locations.sort(this.sortByCity);
    this.updateDropdown();
    this.UI.modal.querySelector('.search-by').innerText = '';
    this.UI.container.getElementsByClassName('search-input')[0].value = '';
  }

  searchNearestDebounce(search, force) {
    let _this = this;
    clearTimeout(this._searchTimeoutId);
    /* if enter is pressed no need to wait */
    if (force) {
      this.searchNearest(search);
      return;
    }
    this._searchTimeoutId = setTimeout(this.searchNearest.bind(_this), 1000, search);
  }

  searchNearest(search) {
    let _this = this;
    clearTimeout(_this._searchTimeoutId);
    /* reset dropdown if search is empty */
    if (!search.length) {
      this.resetSearch();
    }

    let oReq = new XMLHttpRequest();
    /* access itella class inside response handler */
    oReq.itella = this;
    oReq.addEventListener('loadend', this._handleResponse);
    oReq.open('GET', "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?singleLine=" + search + "&sourceCountry=" + this.country + "&category=&outFields=Postal&maxLocations=1&forStorage=false&f=pjson");
    oReq.send();
  }

  _handleResponse() {
    let _this = this.itella;
    let search_by = _this.UI.modal.querySelector('.search-by');
    if (this.status != 200) {
      search_by.innerText = _this.strings.nothing_found;
      return false;
    }

    let json = JSON.parse(this.responseText);

    if (_this._isDebug) {
      console.log('GEOCODE RESPONSE:', json);
    }
    if (json.candidates != undefined && json.candidates.length > 0) {
      _this._isSearchResult = true;
      search_by.innerText = json.candidates[0].address;
      _this.addDistance({ lat: json.candidates[0].location.y, lon: json.candidates[0].location.x });
      return true;
    }

    search_by.innerText = _this.strings.nothing_found;
    return false;
  }

  addDistance(origin) {
    let _this = this;
    this.locations.forEach(function (loc) {
      loc.distance = _this.calculateDistance(origin, loc.location);
    });
    this.locations.sort(this.sortByDistance);
    this.updateDropdown();
  }

  deg2rad(degress) {
    return degress * Math.PI / 180;
  }

  rad2deg(radians) {
    return radians * 180 / Math.PI;
  }

  calculateDistance(loc1, loc2) {
    let distance = NaN;
    if ((loc1.lat == loc2.$lat) && (loc.lon == loc2.lon)) {
      return 0;
    } else {
      let theta = loc1.lon - loc2.lon;
      let dist = Math.sin(this.deg2rad(loc1.lat)) * Math.sin(this.deg2rad(loc2.lat))
        + Math.cos(this.deg2rad(loc1.lat)) * Math.cos(this.deg2rad(loc2.lat)) * Math.cos(this.deg2rad(theta));
      dist = Math.acos(dist);
      dist = this.rad2deg(dist);
      distance = dist * 60 * 1.1515 * 1.609344;
    }
    return distance;
  }

  registerCallback(callback) {
    if (typeof callback !== 'function') {
      return false;
    }

    return this.callbackList.push(callback);
  }

  /**
   * To work with IE11 we cant use array.find() so this replaces it for our simple usecase
   * @param {Function} checkFn 
   * @param {Array} array 
   */
  findLocInArray(checkFn, array) {
    for (var i = 0; i < array.length; i++) {
      if (checkFn(array[i])) {
        return array[i];
      }
    }
    return undefined;
  }

  setSelection(id, manual = false) {
    let location = this.getLocationByPupCode(id);
    if (!location) { // try looking by ID
      location = this.getLocationById(id);
    }

    if (typeof location !== 'undefined') {
      this.selectedPoint = location;
      this.renderPointInfo(location);
      this.setActiveMarkerByTerminalId(location.id);
      this.setMapView(location.location, this.ZOOM_SELECTED);
      this.submitSelection(manual);
    }
  }

  submitSelection(manual = true) {
    /* make sure there is something selected */
    if (this.selectedPoint == null) {
      return false;
    }
    let _this = this;
    if (this.callbackList.length > 0) {
      this.callbackList.forEach(function (callback) {
        callback.call(_this, manual);
      });
    }
    let selectedEl = this.UI.container.getElementsByClassName('itella-chosen-point')[0];
    selectedEl.innerText = this.selectedPoint.publicName + ', ' + this.selectedPoint.address.address;

    this.hideEl(this.UI.modal);
  }

  renderPointInfo(location) {
    this.locations.forEach(loc => loc._li.classList.remove('active'));
    location._li.classList.add('active');

    let pointInfo = this.UI.modal.querySelector('.point-info');
    let workhours = pointInfo.querySelector('.workhours ol');
    let contacts = pointInfo.querySelector('.contacts ul');

    let openingTimes = [];
    location.openingTimes.forEach(function (time) {
      openingTimes[time.weekday] = { from: time.timeFrom, to: time.timeTo };
    });
    let openHTML = '<div>' + this.strings.no_information + '</div>';
    if (openingTimes.length) {
      openHTML = openingTimes.map(function (time) {
        return `<li>${time.from} - ${time.to}</li>`;
      }).join('\n');
    }
    workhours.innerHTML = openHTML;

    let contactHTML = '<div>' + this.strings.no_information + '</div>';
    contactHTML = `
      <li>${location.address.streetName} ${location.address.streetNumber},</li>
      <li>${location.address.municipality} ${location.address.postalCode}</li>
    `;
    if (location.locationName !== null) {
      contactHTML += `<li>${location.locationName}</li>`;
    }
    if (location.customerServicePhoneNumber !== null) {
      contactHTML += `<li>${location.customerServicePhoneNumber}</li>`;
    }
    if (location.additionalInfo !== null) {
      contactHTML += `<li>${location.additionalInfo}</li>`;
    }
    contacts.innerHTML = contactHTML;

    var drpd = this.UI.modal.querySelector('.itella-select .dropdown');
    drpd.innerText = location.publicName + ', ' + location.address.address;

    return this;
  }

  setActiveMarkerByTerminalId(id) {
    // No longer used
    // this._markerLayer.eachLayer(function (icon) {
    //   if (typeof icon._icon !== 'undefined') {
    //     L.DomUtil.removeClass(icon._icon, "active");
    //     if (icon.options.pickupPointId === id) {
    //       L.DomUtil.addClass(icon._icon, "active");
    //     }
    //   }
    // });
  }

  getLocationById(id) {
    return this.findLocInArray(function (loc) {
      return loc.id == id;
    }, this.locations);
  }

  getLocationByPupCode(id) {
    return this.findLocInArray(function (loc) {
      return loc.pupCode == id;
    }, this.locations);
  }

  createElement(tag, classList = []) {
    let el = document.createElement(tag);
    if (classList.length) {
      classList.forEach(elClass => el.classList.add(elClass))
    }
    return el;
  }

  setupLeafletMap(rootEl) {
    this._map = L.map(rootEl, {
      zoomControl: false,
      minZoom: this.ZOOM_MIN,
      maxZoom: this.ZOOM_MAX
    }).setActiveArea({
      position: 'absolute',
      top: '0',
      bottom: '0',
      left: '370px', // it will be changed dynamicaly
      right: '0'
    });
    new L.Control.Zoom({ position: 'bottomright' }).addTo(this._map);

    let Icon = L.Icon.extend({
      options: {
        iconSize: [29, 34],
        iconAnchor: [15, 34],
        popupAnchor: [-3, -76]
      }
    });
    this._pickupIcon = new Icon({ iconUrl: this.images_url + 'marker.png' });

    L.tileLayer('https://map.plugins.itella.com/tile/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.mijora.lt">Mijora</a>' +
        ' | Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
    }).addTo(this._map);
    this._markerLayer = L.markerClusterGroup({
      zoomToBoundsOnClick: false
    });
    this._map.addLayer(this._markerLayer);
    return this;
  }

  setMapView(targetLatLng, targetZoom) {
    if (window.matchMedia("(min-width: 769px)").matches) {
      let offset = this.getElClientWidth(this.UI.container.getElementsByClassName('itella-card')[0]); // / 2;
      this._map._viewport.style.left = offset + 'px';
      // let targetPoint = this._map.project(targetLatLng, targetZoom).subtract([offset, 0]);
      // targetLatLng = this._map.unproject(targetPoint, targetZoom);
    } else {
      this._map._viewport.style.left = '0';
    }
    this._map.setView(targetLatLng, targetZoom);
  }

  getElClientWidth(el) {
    return el.clientWidth;
  }

  addMarker(latLong, id) {
    let marker = L.marker(latLong, { icon: this._pickupIcon, pickupPointId: id });
    this._markerLayer.addLayer(marker);
    return marker;
  }

  updateMapMarkers() {
    let _this = this;
    if (this._markerLayer !== null) {
      this._markerLayer.clearLayers();
    }

    /* add markers to marker layer and link icon in locations list */
    this.locations.forEach(function (location) {
      location._marker = _this.addMarker(location.location, location.id);
    });

    return this;
  }

  sortByCity(a, b) {
    let result = a.address.municipality.toLowerCase().localeCompare(b.address.municipality.toLowerCase());
    if (result == 0) {
      result = a.publicName.toLowerCase().localeCompare(b.publicName.toLowerCase());
    }
    return result;
  }

  sortByDistance(a, b) {
    return a.distance - b.distance;
  }

  setLocations(locations, update = true) {
    /* clone for inner use */
    this.locations = Array.isArray(locations) ? JSON.parse(JSON.stringify(locations)) : [];
    this.locations.sort(this.sortByCity);

    /* calculate defaultMapPos */
    let _latlongArray = [];
    this.locations.forEach(loc => {
      _latlongArray.push(loc.location);
    });
    if (_latlongArray.length > 0) {
      let bounds = new L.LatLngBounds(_latlongArray);
      this._defaultMapPos = bounds.getCenter();
    }

    if (update) {
      this.updateMapMarkers();
      this.updateDropdown();
    }
    return this;
  }

  getLocationCount() {
    return this.locations.length;
  }

  updateDropdown() {
    let _this = this;
    /**
     * @type HTMLElement
     */
    let dropdown = this.UI.modal.querySelector('.itella-select .dropdown-inner ul');

    if (!this.locations.length) {
      dropdown.innerHTML = '<li class="city">' + this.strings.no_pickup_points + '</li>';
      return this;
    }

    dropdown.innerHTML = '';
    let listHTML = [];
    let city = false;
    this.locations.forEach(function (loc) {
      if (city !== loc.address.municipality.toLowerCase()) {
        city = loc.address.municipality.toLowerCase();
        let cityEl = _this.createElement('li', ['city']);
        cityEl.innerText = loc.address.municipality;
        listHTML.push(cityEl);
      }

      /* check if we allready have html object, otherwise create new one */
      let li = Object.prototype.toString.call(loc._li) == '[object HTMLLIElement]' ? loc._li : _this.createElement('li');
      li.innerHTML = loc.publicName + ', ' + loc.address.address;
      if (typeof loc.distance != 'undefined') {
        let span = _this.createElement('span');
        span.innerText = loc.distance.toFixed(2);
        li.appendChild(span);
      }
      li.dataset.id = loc.id;
      listHTML.push(li);
      loc._li = li;
    });
    //dropdown.append(listHTML);
    let docFrag = document.createDocumentFragment();
    listHTML.forEach(el => docFrag.appendChild(el));
    dropdown.appendChild(docFrag);
    if (this.selectedPoint != null) {
      this.selectedPoint._li.classList.add('active');
    }

    return this;
  }

  setCountry(country_iso2_code) {
    this.country = country_iso2_code;
    return this;
  }

  setDebug(isOn = false) {
    this._isDebug = isOn;
    return this;
  }
}

window.itellaRegisterLeafletPlugins = function () {
  /**
   * Leaflet-active-area plugin
   * https://github.com/Mappy/Leaflet-active-area
   * License: Apache 2.0
   * 
   */

  (function (previousMethods) {
    if (typeof previousMethods === 'undefined') {
      // Defining previously that object allows you to use that plugin even if you have overridden L.map
      previousMethods = {
        getCenter: L.Map.prototype.getCenter,
        setView: L.Map.prototype.setView,
        flyTo: L.Map.prototype.flyTo,
        setZoomAround: L.Map.prototype.setZoomAround,
        getBoundsZoom: L.Map.prototype.getBoundsZoom,
        PopupAdjustPan: L.Popup.prototype._adjustPan,
        RendererUpdate: L.Renderer.prototype._update
      };
    }


    L.Map.include({
      getBounds: function () {
        if (this._viewport) {
          return this.getViewportLatLngBounds()
        } else {
          var bounds = this.getPixelBounds(),
            sw = this.unproject(bounds.getBottomLeft()),
            ne = this.unproject(bounds.getTopRight());

          return new L.LatLngBounds(sw, ne);
        }
      },

      getViewport: function () {
        return this._viewport;
      },

      getViewportBounds: function () {
        var vp = this._viewport,
          topleft = L.point(vp.offsetLeft, vp.offsetTop),
          vpsize = L.point(vp.clientWidth, vp.clientHeight);

        if (vpsize.x === 0 || vpsize.y === 0) {
          //Our own viewport has no good size - so we fallback to the container size:
          vp = this.getContainer();
          if (vp) {
            topleft = L.point(0, 0);
            vpsize = L.point(vp.clientWidth, vp.clientHeight);
          }

        }

        return L.bounds(topleft, topleft.add(vpsize));
      },

      getViewportLatLngBounds: function () {
        var bounds = this.getViewportBounds();
        return L.latLngBounds(this.containerPointToLatLng(bounds.min), this.containerPointToLatLng(bounds.max));
      },

      getOffset: function () {
        var mCenter = this.getSize().divideBy(2),
          vCenter = this.getViewportBounds().getCenter();

        return mCenter.subtract(vCenter);
      },

      getCenter: function (withoutViewport) {
        var center = previousMethods.getCenter.call(this);

        if (this.getViewport() && !withoutViewport) {
          var zoom = this.getZoom(),
            point = this.project(center, zoom);
          point = point.subtract(this.getOffset());

          center = this.unproject(point, zoom);
        }

        return center;
      },

      setView: function (center, zoom, options) {
        center = L.latLng(center);
        zoom = zoom === undefined ? this._zoom : this._limitZoom(zoom);

        if (this.getViewport()) {
          var point = this.project(center, this._limitZoom(zoom));
          point = point.add(this.getOffset());
          center = this.unproject(point, this._limitZoom(zoom));
        }

        return previousMethods.setView.call(this, center, zoom, options);
      },

      flyTo: function (targetCenter, targetZoom, options) {
        targetCenter = L.latLng(targetCenter);
        targetZoom = targetZoom === undefined ? startZoom : targetZoom;

        if (this.getViewport()) {
          var point = this.project(targetCenter, this._limitZoom(targetZoom));
          point = point.add(this.getOffset());
          targetCenter = this.unproject(point, this._limitZoom(targetZoom));
        }

        options = options || {};
        if (options.animate === false || !L.Browser.any3d) {
          return this.setView(targetCenter, targetZoom, options);
        }

        this._stop();

        var from = this.project(previousMethods.getCenter.call(this)),
          to = this.project(targetCenter),
          size = this.getSize(),
          startZoom = this._zoom;


        var w0 = Math.max(size.x, size.y),
          w1 = w0 * this.getZoomScale(startZoom, targetZoom),
          u1 = (to.distanceTo(from)) || 1,
          rho = 1.42,
          rho2 = rho * rho;

        function r(i) {
          var s1 = i ? -1 : 1,
            s2 = i ? w1 : w0,
            t1 = w1 * w1 - w0 * w0 + s1 * rho2 * rho2 * u1 * u1,
            b1 = 2 * s2 * rho2 * u1,
            b = t1 / b1,
            sq = Math.sqrt(b * b + 1) - b;

          // workaround for floating point precision bug when sq = 0, log = -Infinite,
          // thus triggering an infinite loop in flyTo
          var log = sq < 0.000000001 ? -18 : Math.log(sq);

          return log;
        }

        function sinh(n) { return (Math.exp(n) - Math.exp(-n)) / 2; }
        function cosh(n) { return (Math.exp(n) + Math.exp(-n)) / 2; }
        function tanh(n) { return sinh(n) / cosh(n); }

        var r0 = r(0);

        function w(s) { return w0 * (cosh(r0) / cosh(r0 + rho * s)); }
        function u(s) { return w0 * (cosh(r0) * tanh(r0 + rho * s) - sinh(r0)) / rho2; }

        function easeOut(t) { return 1 - Math.pow(1 - t, 1.5); }

        var start = Date.now(),
          S = (r(1) - r0) / rho,
          duration = options.duration ? 1000 * options.duration : 1000 * S * 0.8;

        function frame() {
          var t = (Date.now() - start) / duration,
            s = easeOut(t) * S;

          if (t <= 1) {
            this._flyToFrame = L.Util.requestAnimFrame(frame, this);

            this._move(
              this.unproject(from.add(to.subtract(from).multiplyBy(u(s) / u1)), startZoom),
              this.getScaleZoom(w0 / w(s), startZoom),
              { flyTo: true });

          } else {
            this
              ._move(targetCenter, targetZoom)
              ._moveEnd(true);
          }
        }

        this._moveStart(true, options.noMoveStart);

        frame.call(this);
        return this;
      },


      setZoomAround: function (latlng, zoom, options) {
        var vp = this.getViewport();

        if (vp) {
          var scale = this.getZoomScale(zoom),
            viewHalf = this.getViewportBounds().getCenter(),
            containerPoint = latlng instanceof L.Point ? latlng : this.latLngToContainerPoint(latlng),

            centerOffset = containerPoint.subtract(viewHalf).multiplyBy(1 - 1 / scale),
            newCenter = this.containerPointToLatLng(viewHalf.add(centerOffset));

          return this.setView(newCenter, zoom, { zoom: options });
        } else {
          return previousMethods.setZoomAround.call(this, latlng, zoom, options);
        }
      },

      getBoundsZoom: function (bounds, inside, padding) { // (LatLngBounds[, Boolean, Point]) -> Number
        bounds = L.latLngBounds(bounds);
        padding = L.point(padding || [0, 0]);

        var zoom = this.getZoom() || 0,
          min = this.getMinZoom(),
          max = this.getMaxZoom(),
          nw = bounds.getNorthWest(),
          se = bounds.getSouthEast(),
          vp = this.getViewport(),
          size = (vp ? L.point(vp.clientWidth, vp.clientHeight) : this.getSize()).subtract(padding),
          boundsSize = this.project(se, zoom).subtract(this.project(nw, zoom)),
          snap = L.Browser.any3d ? this.options.zoomSnap : 1;

        var scale = Math.min(size.x / boundsSize.x, size.y / boundsSize.y);

        zoom = this.getScaleZoom(scale, zoom);

        if (snap) {
          zoom = Math.round(zoom / (snap / 100)) * (snap / 100); // don't jump if within 1% of a snap level
          zoom = inside ? Math.ceil(zoom / snap) * snap : Math.floor(zoom / snap) * snap;
        }

        return Math.max(min, Math.min(max, zoom));
      }
    });

    L.Map.include({
      setActiveArea: function (css, keepCenter, animate) {
        var center;
        if (keepCenter && this._zoom) {
          // save center if map is already initialized
          // and keepCenter is passed
          center = this.getCenter();
        }

        if (!this._viewport) {
          //Make viewport if not already made
          var container = this.getContainer();
          this._viewport = L.DomUtil.create('div', '');
          container.insertBefore(this._viewport, container.firstChild);
        }

        if (typeof css === 'string') {
          this._viewport.className = css;
        } else {
          L.extend(this._viewport.style, css);
        }

        if (center) {
          this.setView(center, this.getZoom(), { animate: !!animate });
        }
        return this;
      }
    });

    L.Renderer.include({
      _onZoom: function () {
        this._updateTransform(this._map.getCenter(true), this._map.getZoom());
      },

      _update: function () {
        previousMethods.RendererUpdate.call(this);
        this._center = this._map.getCenter(true);
      }
    });

    L.GridLayer.include({
      _updateLevels: function () {

        var zoom = this._tileZoom,
          maxZoom = this.options.maxZoom;

        if (zoom === undefined) { return undefined; }

        for (var z in this._levels) {
          if (this._levels[z].el.children.length || z === zoom) {
            this._levels[z].el.style.zIndex = maxZoom - Math.abs(zoom - z);
          } else {
            L.DomUtil.remove(this._levels[z].el);
            this._removeTilesAtZoom(z);
            delete this._levels[z];
          }
        }

        var level = this._levels[zoom],
          map = this._map;

        if (!level) {
          level = this._levels[zoom] = {};

          level.el = L.DomUtil.create('div', 'leaflet-tile-container leaflet-zoom-animated', this._container);
          level.el.style.zIndex = maxZoom;

          level.origin = map.project(map.unproject(map.getPixelOrigin()), zoom).round();
          level.zoom = zoom;

          this._setZoomTransform(level, map.getCenter(true), map.getZoom());

          // force the browser to consider the newly added element for transition
          L.Util.falseFn(level.el.offsetWidth);
        }

        this._level = level;

        return level;
      },

      _resetView: function (e) {
        var animating = e && (e.pinch || e.flyTo);
        this._setView(this._map.getCenter(true), this._map.getZoom(), animating, animating);
      },

      _update: function (center) {
        var map = this._map;
        if (!map) { return; }
        var zoom = this._clampZoom(map.getZoom());

        if (center === undefined) { center = map.getCenter(this); }
        if (this._tileZoom === undefined) { return; }    // if out of minzoom/maxzoom

        var pixelBounds = this._getTiledPixelBounds(center),
          tileRange = this._pxBoundsToTileRange(pixelBounds),
          tileCenter = tileRange.getCenter(),
          queue = [];

        for (var key in this._tiles) {
          this._tiles[key].current = false;
        }

        // _update just loads more tiles. If the tile zoom level differs too much
        // from the map's, let _setView reset levels and prune old tiles.
        if (Math.abs(zoom - this._tileZoom) > 1) { this._setView(center, zoom); return; }

        // create a queue of coordinates to load tiles from
        for (var j = tileRange.min.y; j <= tileRange.max.y; j++) {
          for (var i = tileRange.min.x; i <= tileRange.max.x; i++) {
            var coords = new L.Point(i, j);
            coords.z = this._tileZoom;

            if (!this._isValidTile(coords)) { continue; }

            var tile = this._tiles[this._tileCoordsToKey(coords)];
            if (tile) {
              tile.current = true;
            } else {
              queue.push(coords);
            }
          }
        }

        // sort tile queue to load tiles in order of their distance to center
        queue.sort(function (a, b) {
          return a.distanceTo(tileCenter) - b.distanceTo(tileCenter);
        });

        if (queue.length !== 0) {
          // if its the first batch of tiles to load
          if (!this._loading) {
            this._loading = true;
            // @event loading: Event
            // Fired when the grid layer starts loading tiles
            this.fire('loading');
          }

          // create DOM fragment to append tiles in one batch
          var fragment = document.createDocumentFragment();

          for (i = 0; i < queue.length; i++) {
            this._addTile(queue[i], fragment);
          }

          this._level.el.appendChild(fragment);
        }
      }
    });

    L.Popup.include({
      _adjustPan: function () {
        if (!this._map._viewport) {
          previousMethods.PopupAdjustPan.call(this);
        } else {
          if (!this.options.autoPan || (this._map._panAnim && this._map._panAnim._inProgress)) { return; }

          var map = this._map,
            vp = map._viewport,
            containerHeight = this._container.offsetHeight,
            containerWidth = this._containerWidth,
            vpTopleft = L.point(vp.offsetLeft, vp.offsetTop),

            layerPos = new L.Point(
              this._containerLeft - vpTopleft.x,
              - containerHeight - this._containerBottom - vpTopleft.y);

          if (this._zoomAnimated) {
            layerPos._add(L.DomUtil.getPosition(this._container));
          }

          var containerPos = map.layerPointToContainerPoint(layerPos),
            padding = L.point(this.options.autoPanPadding),
            paddingTL = L.point(this.options.autoPanPaddingTopLeft || padding),
            paddingBR = L.point(this.options.autoPanPaddingBottomRight || padding),
            size = L.point(vp.clientWidth, vp.clientHeight),
            dx = 0,
            dy = 0;

          if (containerPos.x + containerWidth + paddingBR.x > size.x) { // right
            dx = containerPos.x + containerWidth - size.x + paddingBR.x;
          }
          if (containerPos.x - dx - paddingTL.x < 0) { // left
            dx = containerPos.x - paddingTL.x;
          }
          if (containerPos.y + containerHeight + paddingBR.y > size.y) { // bottom
            dy = containerPos.y + containerHeight - size.y + paddingBR.y;
          }
          if (containerPos.y - dy - paddingTL.y < 0) { // top
            dy = containerPos.y - paddingTL.y;
          }

          // @namespace Map
          // @section Popup events
          // @event autopanstart
          // Fired when the map starts autopanning when opening a popup.
          if (dx || dy) {
            map
              .fire('autopanstart')
              .panBy([dx, dy]);
          }
        }
      }
    });
  })(window.leafletActiveAreaPreviousMethods);

}