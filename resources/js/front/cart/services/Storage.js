class AgStorage {


    constructor() {
        this.vue = new Vue();
    }

    /**
     * @returns {string}
     */
    getName() {
        return 'agrocon_cart';
    }

    /**
     * @returns {number} Hours.
     */
    expire() {
        return 24;
    }

    /**
     *
     * @returns {{total: number, subtotal: number, count: number, cart: []}}
     */
    structureStorage() {
        return {
            cart: [],
            count: 0,
            subtotal: 0,
            conditions: [],
            total: 0
        }
    }

    /**
     *
     * @returns {any} localStorage
     */
    asJson() {
        return JSON.parse(localStorage.getItem(this.getName()) || this.structureStorage());
    }


    set(key, value) {
        var _array = this.asJson();

        /*if (_array.length > 0) {
            array.forEach(function (item) {
                _array.push(item);
            })
            localStorage.setItem(this.getName(), JSON.stringify(_array));
        } else {
            localStorage.setItem(this.getName(), JSON.stringify(array));
        }*/
    }



    get(key = null) {
        let _array = this.asJson();

        console.log('AgStorage get() ', _array);
    }

    remove() {
        localStorage.removeItem(this.getName());
    }

    trashAll() {
        localStorage.clear();
    }

    fetchCount() {
        var array = JSON.parse(localStorage.getItem(this.getName()) || '[]');
        return array.length;
    }
}
