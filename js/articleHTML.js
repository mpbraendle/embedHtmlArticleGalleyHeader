/**
 *
 * @file plugins/generic/embedHtmlArticleGalleyHeader/js/articleHTML.js
 *
 * Copyright (c) 2023 Belgorod State University
 * Copyright (c) 2023 Eryomin Oleg
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 */

const heads = document.querySelectorAll("h1, h2, h3, h4, h5, h6");
const imgs = document.querySelectorAll("img");
let paddings = new Map();
let value = 0;
for (i = 1; i <= 6; i++){
    let key = "H" + i;
    paddings.set(key, value);
    value  +=  20;
}
const ul = document.getElementById("heads");
heads.forEach((h, index) => {
    let id = "head" + index;
    h.id = id;
    let li = document.createElement("li");
    li.style = "padding-left:" + paddings.get(h.tagName) + "px";
    let a  = document.createElement("a");
    a.href = "#";
    a.id = index;
    a.addEventListener("click", function() {
        const id = "head" + this.id;
        document.getElementById(id).scrollIntoView();
    }, false);
    if (index !== 0) {
        a.innerHTML = h.innerText;
    } else {
        a.innerHTML = "Article title";
    }
    if (h.innerText !== "Contents"){
        if (h.innerText){
            li.append(a);
            ul.append(li);
        }
    }
});
imgs.forEach((img) => {
    img.tabIndex = "0";
});