/**
 * Depedencies
 */
var gulp                = require('gulp'),
    sass                = require('gulp-sass'),
    minifyCss           = require('gulp-minify-css'),
    uglify              = require('gulp-uglify'),
    rename              = require('gulp-rename'),
    concat              = require('gulp-concat'),
    compass             = require('gulp-compass');

/**
 * Default
 */
gulp.task('default', function() {
    console.log('Gulp start');

    gulp.watch(['./app/Resources/assets/sass/*.scss', './app/Resources/assets/sass/**/*.scss'], ['sass']);
    gulp.watch('./app/Resources/assets/sass/*.css', ['minify-css']);
    gulp.watch(['./app/Resources/assets/scripts/*.js', './app/Resources/assets/scripts/**/*.js'], ['minify-js']);

    console.log('Gulp end');
});

/**
 * Compile SASS to CSS
 */
gulp.task('sass', function () {
    console.log('Compile SASS to CSS');

    gulp.src(['./app/Resources/assets/sass/*.scss', './app/Resources/assets/sass/**/*.scss'])
        .pipe(compass({
            css: './app/Resources/assets/sass/',
            sass: './app/Resources/assets/sass/'
        }));
});

/**
 * Minify CSS
 */
gulp.task('minify-css', function() {
    console.log('Minify CSS');

    return gulp.src(['./app/Resources/assets/sass/*.css'])
        .pipe(minifyCss({semanticMerging: true}))
        .pipe(rename({extname: '.min.css'}))
        .pipe(gulp.dest('./web/public/'));
});

/**
 * Minify JS
 */
gulp.task('minify-js', function() {
    console.log('Minify JS');

    return gulp.src([
        './app/Resources/assets/scripts/app.js'
    ])
    .pipe(concat('app.js'))
    .pipe(rename({extname: '.min.js'}))
    .pipe(uglify())
    .pipe(gulp.dest('./web/public/'));
});