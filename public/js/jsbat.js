/*! Multi JS Bats – po cijelom ekranu – Eric Grange mod */
(function () {
    var d = document, w = window, r = Math.random;

    var COUNT = 10; // broj šišmiša
    var SIZE_MIN = 24, SIZE_MAX = 64;
    var SPEED_MIN = 2500, SPEED_MAX = 6000;

    var SRC = 'data:image/gif;base64,R0lGODlhMAAwAJECAAAAAEJCQv///////yH/C05FVFNDQVBFMi4wAwEAAAAh+QQJAQACACwAAAAAMAAwAAACdpSPqcvtD6NcYNpbr4Z5ewV0UvhRohOe5UE+6cq0carCgpzQuM3ut16zvRBAH+/XKQ6PvaQyCFs+mbnWlEq0FrGi15XZJSmxP8OTRj4DyWY1lKdmV8fyLL3eXOPn6D3f6BcoOEhYaHiImKi4yNjo+AgZKTl5WAAAIfkECQEAAgAsAAAAADAAMAAAAnyUj6nL7Q+jdCDWicF9G1vdeWICao05ciUVpkrZIqjLwCdI16s+5wfck+F8JOBiR/zZZAJk0mAsDp/KIHRKvVqb2KxTu/Vdvt/nGFs2V5Bpta3tBcKp8m5WWL/z5PpbtH/0B/iyNGh4iJiouMjY6PgIGSk5SVlpeYmZqVkAACH5BAkBAAIALAAAAAAwADAAAAJhlI+py+0Po5y02ouz3rz7D4biSJbmiabq6gCs4B5AvM7GTKv4buby7vsAbT9gZ4h0JYmZpXO4YEKeVCk0QkVUlw+uYovE8ibgaVBSLm1Pa3W194rL5/S6/Y7P6/f8vp9SAAAh+QQJAQACACwAAAAAMAAwAAACZZSPqcvtD6OctNqLs968+w+G4kiW5omm6ooALeCusAHHclyzQs3rOz9jAXuqIRFlPJ6SQWRSaIQOpUBqtfjEZpfMJqmrHIFtpbGze2ZywWu0aUwWEbfiZvQdD4sXuWUj7gPos1EAACH5BAkBAAIALAAAAAAwADAAAAJrlI+py+0Po5y02ouz3rz7D4ZiCIxUaU4Amjrr+rDg+7ojXTdyh+e7kPP0egjabGg0EIVImHLJa6KaUam1aqVynNNsUvPTQjO/J84cFA3RzlaJO2495TF63Y7P6/f8vv8PGCg4SFhoeIg4UQAAIfkEBQEAAgAsAAAAADAAMAAAAnaUj6nL7Q+jXGDaW6+GeXsFdFL4UaITnuVBPunKtHGqwoKc0LjN7rdes70QQB/v1ykOj72kMghbPpm51pRKtBaxoteV2SUpsT/Dk0Y+A8lmNZSnZlfH8iy93lzj5+g93+gXKDhIWGh4iJiouMjY6PgIGSk5eVgAADs=';

    function rand(a, b) { return a + r() * (b - a); }

    function createBat() {
        var wrap = d.createElement('div');
        var img = d.createElement('img');
        var zs = wrap.style;

        var size = Math.round(rand(SIZE_MIN, SIZE_MAX));
        var x = rand(0, w.innerWidth);
        var y = rand(0, w.innerHeight);

        wrap.appendChild(img);
        img.src = SRC;
        img.width = size;
        img.height = size * 0.75;

        zs.position = 'fixed';
        zs.left = 0;
        zs.top = 0;
        zs.opacity = 0;
        zs.pointerEvents = 'none';
        zs.zIndex = 9999;
        d.body.appendChild(wrap);

        function fly() {
            var nx = rand(0, w.innerWidth - 100);
            var ny = rand(0, w.innerHeight - 100);
            var dx = nx - x, dy = ny - y;
            var dist = Math.sqrt(dx * dx + dy * dy);
            var dur = rand(SPEED_MIN, SPEED_MAX) * (dist / 400);

            zs.opacity = 1;
            zs.transition = zs.webkitTransition = (dur / 1000) + 's linear';
            zs.transform = zs.webkitTransform = 'translate(' + nx + 'px,' + ny + 'px)';

            img.style.transform = (x > nx) ? '' : 'scaleX(-1)';

            x = nx; y = ny;
            setTimeout(fly, dur);
        }

        setTimeout(fly, rand(0, 3000));
    }

    for (var i = 0; i < 10; i++) createBat();
})();
