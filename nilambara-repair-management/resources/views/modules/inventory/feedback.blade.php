@if(session('status'))<div role="status" class="mb-4 rounded-xl bg-teal-50 p-4 text-teal-800">{{ session('status') }}</div>
@endif

@if($errors->any())<div role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-red-800"><ul>
@foreach($errors->all() as $message)<li>{{ $message }}</li>
@endforeach</ul></div>
@endif
