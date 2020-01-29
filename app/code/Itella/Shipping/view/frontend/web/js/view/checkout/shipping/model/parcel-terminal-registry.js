define(
    [],
    function() {
        "use strict";
        var cache = [];
        return {
            get: function(addressKey) {
                //console.log(addressKey);
                if (cache[addressKey]) {
                    return cache[addressKey];
                }
                return false;
            },
            set: function(addressKey, data) {
                //console.log(data);
                cache[addressKey] = data;
            }
        };
    }
);