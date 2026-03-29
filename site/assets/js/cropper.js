// npm package: cropperjs
// github link: https://github.com/fengyuanchen/cropperjs

'use strict';

(function () {

  const cropperImage = document.querySelector('#croppingImage'),
    cropperSelection = document.querySelector('#cropperSelection'),
    img_w = document.querySelector('.img-w'),
    cropBtn = document.querySelector('.crop'),
    croppedImg = document.querySelector('.cropped-img'),
    dwn = document.querySelector('.download'),
    upload = document.querySelector('#cropperImageUpload');

  // on change show image with crop options
  upload.addEventListener('change', function (e) {
    if (e.target.files.length) {
      console.log(e.target.files[0]);
      const fileType = e.target.files[0].type;
      if (fileType === 'image/gif' || fileType === 'image/jpeg' || fileType === 'image/png') {
        // start file reader
        const reader = new FileReader();
        reader.onload = function (e) {
          if (e.target.result) {
            cropperImage.src = e.target.result;
          }
        };
        reader.readAsDataURL(e.target.files[0]);
      } else {
        alert("Selected file type is not supported. Please try again")
      }
    }
  });

  // crop on click
  cropBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // get result to data uri
    cropperSelection.$toCanvas({
      width: img_w.value // input value
    }).then((canvas) => {
      let imgSrc = canvas.toDataURL();
      croppedImg.src = imgSrc;
      dwn.setAttribute('href', imgSrc);
      dwn.download = 'imagename.png';
    });
  });

})();