/* changes the order of the (selected) options in a list
 * list: list-object or id-attribute of one
 * move = up/down:    moves the selected items one position up or down
 * move = top/bottom: moves the selected items to the top/bottom of the list
 * move = invert:     changes the order of the selected items
 */
function ojl_form_select_reorder(list,move){
    if(typeof(list)!='object') list = document.getElementById(list);
    if(list.selectedIndex<0) return; // nothing to do
    n = list.length;
    switch(move){
    case 'up':
	ok = 0; // as long as ok is zero, nothing has to be done
	for(i=0;i<n;i++){
	    if(!list.options[i].selected) ok = 1;
	    else if(ok>0)  ojl_form_select_option_swap(list,i-1,i);
	}
	break;

    case 'down':
	ok = 0; // as long as ok is zero, nothing has to be done
	for(i=n-1;i>=0;i--){
	    if(!list.options[i].selected) ok = 1;
	    else if(ok>0) ojl_form_select_option_swap(list,i+1,i);
	}
	break;
	
    case 'top': case 'bottom':
	i = 0;
	z = n;
	inv = move=='bottom';
	while(i<z){
	    if((inv  && list.options[i].selected) || !(inv || list.options[i].selected)){
		list.options[n] = new Option(list.options[i].text,list.options[i].value,false,inv);
		list.options[i] = null;
		z--;
	    } else i++;
	}
	break;

    case 'invert':
	i = 0;
	j = list.length-1;
	while(i<j){
	    if(!list.options[i].selected) i++;
	    else if(!list.options[j].selected) j--;
	    else ojl_form_select_option_swap(list,i++,j--);
	}
	break;
    }
}

/* swaps to options in a select-list
 * list: pointer to the list
 * i/j: numeric position of the items
 */
function ojl_form_select_option_swap(list,i,j){
    tmp = new Option(list.options[i].text,list.options[i].value,list.options[i].defaultselected,list.options[i].selected);
    list.options[i] = new Option(list.options[j].text,list.options[j].value,list.options[j].defaultselected,list.options[j].selected);
    list.options[j] = tmp;
}


/* sorts the item of a select-list
 * mode (negative means reverse order)
 *  0: shuffle
 *  1: use value
 *  2: use text
 */
function ojl_form_select_sort(list,mode){
    if(typeof(list)!='object') list = document.getElementById(list);
    arr = new Array();
    n = list.length;
    switch(Math.abs(mode)){
    case 0: for(i=0;i<n;i++) arr[i] = {key:i,val:Math.random()}; break;
    case 1: for(i=0;i<n;i++) arr[i] = {key:i,val:list.options[i].value}; break;
    case 2: for(i=0;i<n;i++) arr[i] = {key:i,val:list.options[i].text}; break;
    default: return alert('unkown sorting mode: ' + mode);
    }
    arr.sort(ojl_sort_kv);
    for(i=0;i<n;i++){
	j = arr[mode<0?(n-i-1):i].key;
	list.options[n+i] = new Option(list.options[j].text,list.options[j].value,list.options[j].defaultselected,list.options[j].selected);
    }
    for(i=0;i<n;i++) list.options[0] = null;

}

function ojl_sort_kv(a,b){ return ((a.val<b.val)?-1:(a.val>b.val)?1:0);}

/* moves/copies items between two lists
 * listA/B: list-object or id-attribute of one
 * copy: 0 -> remove from listA;   1 -> keep in listA
 * all:  0 -> only selected items; 1 -> all items
 */
function ojl_form_select_move(listA,listB,copy,all){
    if(typeof(listA)!='object') listA = document.getElementById(listA);
    if(listA.selectedIndex<0 && all==0) return; // nothing to do
    if(typeof(listB)!='object') listB = document.getElementById(listB);
    na = listA.length;
    nb = listB.length;
    i = 0;
    while(i<na){
	if(all==1  || listA.options[i].selected){
	    listB.options[nb++] = new Option(listA.options[i].text,listA.options[i].value,listA.options[i].defaultselected,listA.options[i].selected);
	    if(copy==0){
		listA.options[i] = null;
		na--;
	    } else i++;
	} else i++;
    }
}

/* removes item from a select-list
 * mode = 0: all
 *        1: selected
 *        1: not selected
 *        2: duplicates in key
 *        1: duplicates in value
 */
function ojl_form_select_clean(list,mode){
    if(typeof(list)!='object') list = document.getElementById(list);
    n = list.length;
    switch(mode){
    case 0: 
	for(i=0;i<n;i++) list.options[0] = null; 
	break;
    case 1: 
	for(i=n-1;i>=0;i--) if(list.options[i].selected) list.options[i] = null;
	break;
    case 2: 
	for(i=n-1;i>=0;i--) if(!list.options[i].selected) list.options[i] = null;
	break;
    case 3: 
	for(i=n-1;i>=1;i--) 
	    for(j=0;j<i;j++) 
		if(list.options[i].value==list.options[j].value) {list.options[i] = null; j=i;}
	break;
    case 4: 
	for(i=n-1;i>=1;i--) 
	    for(j=0;j<i;j++) 
		if(list.options[i].text==list.options[j].text)   {list.options[i] = null; j=i;}
	break;
    }
}


function ojl_form_select_add(list,key,value){
    if(typeof(list)!='object') list = document.getElementById(list);
    list.options[list.length] = new Option(value,key,0,1);
}

/* changes selection of a select-list
 * all/none: select all/none
 * first/last: select first/last element
 * invert: invert current selection$
 * previous/next: select previous(next element
 *  add = 1: use list cyclic
 *  add = 2: at least one element is selected (first/last)

 */
function ojl_form_select_selection(list,change,add){
    if(typeof(list)!='object') list = document.getElementById(list);
    n = list.length;
    switch(change){
    case 'all':    for(i=0;i<n;i++) list.options[i].selected = 1; break;
    case 'none':   for(i=0;i<n;i++) list.options[i].selected = 0; break;
    case 'invert': for(i=0;i<n;i++) list.options[i].selected = !list.options[i].selected; break;
    case 'first':  for(i=0;i<n;i++) list.options[i].selected = 0; list.options[0].selected = 1; break;
    case 'last':   for(i=0;i<n;i++) list.options[i].selected = 0; list.options[n-1].selected = 1; break;
    case 'previous':
	tmp = list.options[0].selected;
	for(i=0;i<n-1;i++) list.options[i].selected = list.options[i+1].selected; 
	list.options[n-1].selected = add==1?tmp:0;
	if($add=2 && list.selectedIndex<0) list.options[0].selected = 1;
	break;
    case 'next':
	tmp = list.options[n-1].selected;
	for(i=n-1;i>0;i--) list.options[i].selected = list.options[i-1].selected; 
	list.options[0].selected = add==1?tmp:0;
	if($add=2 && list.selectedIndex<0) list.options[n-1].selected = 1;
	break;
    }
}