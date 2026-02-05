'use strict';
var gulp = require('gulp');
var $ = require('gulp-load-plugins')();

var del = require('del');
var buffer = require('vinyl-buffer');
var mainBowerFiles = require('main-bower-files');
var merge2 = require('merge2');


var publicRoot = 'www';
var staticRoot = 'static';

var publicImages = publicRoot + '/images',
    staticImages = staticRoot + '/images',
    publicScripts = publicRoot + '/js',
    staticScripts = staticRoot + '/scripts',
    publicStyles = publicRoot + '/css',
    staticStyles = staticRoot + '/styles';


// remove files from target folders
gulp.task('clean', function () {
    del([
        publicScripts + '/*',
        publicStyles + '/*',
        publicImages + '/*'
    ]);
});

// styles
gulp.task('styles', function () {
    var mainBowerFiles = require('main-bower-files');

    var bowerStreamCSS = gulp.src(mainBowerFiles())
        .pipe($.filter(['**/*.css']))
        .pipe($.sourcemaps.init({loadMaps: true}))
        .pipe($.concat('bower.css'))
        .pipe($.sourcemaps.write());

    var bowerStreamSCSS = gulp.src(mainBowerFiles())
        .pipe($.filter(['**/*.scss']))
        .pipe($.sass())
        .pipe($.concat('bower.scss.css'));

    var styleStream = gulp.src(staticStyles + '/style.scss')
        .pipe($.sourcemaps.init({loadMaps: true}))
        .pipe($.sass())
        .pipe($.concat('my.css'))
        .pipe($.sourcemaps.write());

    return merge2(bowerStreamCSS, bowerStreamSCSS, styleStream)
        .pipe($.sourcemaps.init({loadMaps: true}))
        .pipe($.autoprefixer())
        .pipe($.concat('app.css'))
        .pipe($.sourcemaps.write('.'))
        .pipe(gulp.dest(publicStyles));
});


// scripts
gulp.task('scripts', function () {
    var bowerStream = gulp.src(mainBowerFiles())
        .pipe($.filter('**/*.js'))
        .pipe($.concat('bower.js'));

    var scriptsStream = gulp.src([staticScripts + '/app.js', staticScripts + '/*.js'])
        .pipe($.concat('app.js'));

    return merge2(bowerStream, scriptsStream)
        .pipe(buffer())
        .pipe($.sourcemaps.init({loadMaps: true}))
        .pipe($.concat('app.js'))
        .pipe($.sourcemaps.write('.'))
        .pipe(gulp.dest(publicScripts))
});


// images
gulp.task('images', function () {
    return gulp.src(staticImages + '*/*')
        .pipe($.flatten())
        .pipe(gulp.dest(publicImages));
});

// Default Task
gulp.task('development', [
    'clean',
    'styles',
    'scripts',
    'images'
]);