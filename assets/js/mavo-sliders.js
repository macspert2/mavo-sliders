(function () {
	'use strict';

	function MavoSlider(el) {
		var track    = el.querySelector('.mavo-slider__track');
		var slides   = el.querySelectorAll('.mavo-slider__slide');
		var count    = slides.length;
		var interval = parseInt(el.getAttribute('data-interval'), 10) || 5000;
		var current  = 0;

		if (!track || count < 2) return;

		function goTo(n) {
			current = ((n % count) + count) % count;
			track.style.transform = 'translateX(-' + (current * 100) + '%)';
		}

		setInterval(function () { goTo(current + 1); }, interval);
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.mavo-slider').forEach(MavoSlider);
	});
}());
