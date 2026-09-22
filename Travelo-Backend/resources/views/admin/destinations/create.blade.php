@extends('layouts.admin')

@section('title', 'Add New Destination - Travelo Admin')

<!-- Header -->
@section('content')
<!-- Header -->
<header class="h-16 border-b border-slate-200 bg-white flex items-center justify-between px-8">
    <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-slate-400">map</span>
        <h2 class="text-lg font-semibold">Destinations</h2>
        <span class="material-symbols-outlined text-slate-400 text-xs">chevron_right</span>
        <span class="text-slate-900 font-medium">New Destination</span>
    </div>
    <div class="flex items-center gap-4">
        <div class="relative w-64">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
            <input class="w-full bg-slate-100 border-none rounded-lg py-2 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary" placeholder="Search..." type="text"/>
        </div>
        <button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-100 transition-colors">
            <span class="material-symbols-outlined">notifications</span>
        </button>
        <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden">
            <img src="https://ui-avatars.com/api/?name=Admin&background=2463eb&color=fff" alt="Admin" class="w-full h-full object-cover">
        </div>
    </div>
</header>

<!-- Content -->
<div class="flex-1 overflow-y-auto p-8">
    <div class="mb-8">
        <h2 class="text-3xl font-black tracking-tight mb-2">Add New Destination</h2>
        <p class="text-slate-500">Fill in the details to list a new travel spot in the global catalog.</p>
    </div>

    <!-- Form Card -->
    <form action="{{ route('admin.destinations.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-8">
                <!-- Section: General Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-2">
                        <h3 class="text-lg font-bold flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-primary">info</span>
                            General Information
                        </h3>
                        <p class="text-sm text-slate-500 mb-4">Basic details about the location</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700">Destination Name</label>
                        <input name="name" value="{{ old('name') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="e.g. Santorini Sunsets" type="text" required>
                        @error('name')
                            <p class="text-red-500 text-xs">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700">City</label>
                        <input name="city" value="{{ old('city') }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="e.g. Oia" type="text" required>
                        @error('city')
                            <p class="text-red-500 text-xs">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700">Country</label>
                        <select name="country" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" required>
                            <option value="">Select Country</option>
                            <option value="Greece" {{ old('country') == 'Greece' ? 'selected' : '' }}>Greece</option>
                            <option value="Italy" {{ old('country') == 'Italy' ? 'selected' : '' }}>Italy</option>
                            <option value="France" {{ old('country') == 'France' ? 'selected' : '' }}>France</option>
                            <option value="Japan" {{ old('country') == 'Japan' ? 'selected' : '' }}>Japan</option>
                            <option value="Maldives" {{ old('country') == 'Maldives' ? 'selected' : '' }}>Maldives</option>
                            <option value="Indonesia" {{ old('country') == 'Indonesia' ? 'selected' : '' }}>Indonesia</option>
                            <option value="Thailand" {{ old('country') == 'Thailand' ? 'selected' : '' }}>Thailand</option>
                            <option value="Singapore" {{ old('country') == 'Singapore' ? 'selected' : '' }}>Singapore</option>
                            <option value="Malaysia" {{ old('country') == 'Malaysia' ? 'selected' : '' }}>Malaysia</option>
                            <option value="Vietnam" {{ old('country') == 'Vietnam' ? 'selected' : '' }}>Vietnam</option>
                            <option value="Korea" {{ old('country') == 'Korea' ? 'selected' : '' }}>Korea</option>
                            <option value="China" {{ old('country') == 'China' ? 'selected' : '' }}>China</option>
                            <option value="India" {{ old('country') == 'India' ? 'selected' : '' }}>India</option>
                            <option value="Australia" {{ old('country') == 'Australia' ? 'selected' : '' }}>Australia</option>
                            <option value="New Zealand" {{ old('country') == 'New Zealand' ? 'selected' : '' }}>New Zealand</option>
                            <option value="United States" {{ old('country') == 'United States' ? 'selected' : '' }}>United States</option>
                            <option value="United Kingdom" {{ old('country') == 'United Kingdom' ? 'selected' : '' }}>United Kingdom</option>
                            <option value="Spain" {{ old('country') == 'Spain' ? 'selected' : '' }}>Spain</option>
                            <option value="Portugal" {{ old('country') == 'Portugal' ? 'selected' : '' }}>Portugal</option>
                            <option value="Turkey" {{ old('country') == 'Turkey' ? 'selected' : '' }}>Turkey</option>
                            <option value="Egypt" {{ old('country') == 'Egypt' ? 'selected' : '' }}>Egypt</option>
                            <option value="Brazil" {{ old('country') == 'Brazil' ? 'selected' : '' }}>Brazil</option>
                            <option value="Argentina" {{ old('country') == 'Argentina' ? 'selected' : '' }}>Argentina</option>
                            <option value="Mexico" {{ old('country') == 'Mexico' ? 'selected' : '' }}>Mexico</option>
                            <option value="Canada" {{ old('country') == 'Canada' ? 'selected' : '' }}>Canada</option>
                            <option value="Switzerland" {{ old('country') == 'Switzerland' ? 'selected' : '' }}>Switzerland</option>
                            <option value="Germany" {{ old('country') == 'Germany' ? 'selected' : '' }}>Germany</option>
                            <option value="Netherlands" {{ old('country') == 'Netherlands' ? 'selected' : '' }}>Netherlands</option>
                            <option value="Belgium" {{ old('country') == 'Belgium' ? 'selected' : '' }}>Belgium</option>
                            <option value="Austria" {{ old('country') == 'Austria' ? 'selected' : '' }}>Austria</option>
                            <option value="Czech Republic" {{ old('country') == 'Czech Republic' ? 'selected' : '' }}>Czech Republic</option>
                            <option value="Hungary" {{ old('country') == 'Hungary' ? 'selected' : '' }}>Hungary</option>
                            <option value="Poland" {{ old('country') == 'Poland' ? 'selected' : '' }}>Poland</option>
                            <option value="Sweden" {{ old('country') == 'Sweden' ? 'selected' : '' }}>Sweden</option>
                            <option value="Norway" {{ old('country') == 'Norway' ? 'selected' : '' }}>Norway</option>
                            <option value="Denmark" {{ old('country') == 'Denmark' ? 'selected' : '' }}>Denmark</option>
                            <option value="Finland" {{ old('country') == 'Finland' ? 'selected' : '' }}>Finland</option>
                            <option value="Russia" {{ old('country') == 'Russia' ? 'selected' : '' }}>Russia</option>
                            <option value="UAE" {{ old('country') == 'UAE' ? 'selected' : '' }}>UAE</option>
                            <option value="Saudi Arabia" {{ old('country') == 'Saudi Arabia' ? 'selected' : '' }}>Saudi Arabia</option>
                            <option value="South Africa" {{ old('country') == 'South Africa' ? 'selected' : '' }}>South Africa</option>
                            <option value="Morocco" {{ old('country') == 'Morocco' ? 'selected' : '' }}>Morocco</option>
                            <option value="Philippines" {{ old('country') == 'Philippines' ? 'selected' : '' }}>Philippines</option>
                            <option value="Taiwan" {{ old('country') == 'Taiwan' ? 'selected' : '' }}>Taiwan</option>
                            <option value="Hong Kong" {{ old('country') == 'Hong Kong' ? 'selected' : '' }}>Hong Kong</option>
                        </select>
                        @error('country')
                            <p class="text-red-500 text-xs">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700">Average Rating</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-amber-400" style="font-variation-settings: 'FILL' 1;">star</span>
                            <input name="rating" value="{{ old('rating', 4.5) }}" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5 pl-10" max="5" min="0" placeholder="4.5" step="0.1" type="number">
                        </div>
                        @error('rating')
                            <p class="text-red-500 text-xs">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-2 flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-700">Description</label>
                        <textarea name="description" class="w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary text-sm p-2.5" placeholder="Describe the destination's unique features, best time to visit, and local attractions..." rows="4">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-xs">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <hr class="my-8 border-slate-100"/>

                <!-- Section: Media -->
                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-bold flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-primary">image</span>
                            Media Gallery
                        </h3>
                        <p class="text-sm text-slate-500">Upload high-resolution images to attract visitors</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="border-2 border-dashed border-slate-200 rounded-xl p-12 flex flex-col items-center justify-center text-center bg-slate-50/50 hover:bg-slate-50 transition-colors cursor-pointer group">
                                <input type="file" name="image" accept="image/*" class="hidden" id="image-upload">
                                <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                    <span class="material-symbols-outlined text-primary text-3xl">cloud_upload</span>
                                </div>
                                <p class="text-sm font-bold mb-1">Click to upload or drag and drop</p>
                                <p class="text-xs text-slate-400">SVG, PNG, JPG or GIF (max. 10MB)</p>
                            </label>
                            @error('image')
                                <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="relative group h-full">
                            <div class="aspect-square rounded-xl bg-slate-100 overflow-hidden relative" id="image-preview-container" style="display: none;">
                                <img id="image-preview" class="w-full h-full object-cover" src="" alt="Preview">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                    <button class="w-8 h-8 rounded-full bg-white text-slate-900 flex items-center justify-center hover:bg-slate-100 transition-colors" type="button" onclick="document.getElementById('image-upload').click()">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <button class="w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition-colors" type="button" onclick="clearImage()">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>
                            </div>
                            <div class="aspect-square rounded-xl bg-slate-100 overflow-hidden flex items-center justify-center" id="image-placeholder">
                                <span class="material-symbols-outlined text-slate-300 text-5xl">image</span>
                            </div>
                            <p class="text-[10px] uppercase tracking-wider font-bold text-slate-400 mt-2 text-center">Cover Image Preview</p>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                    <a href="{{ route('admin.destinations.index') }}" class="px-6 py-2.5 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-8 py-2.5 rounded-lg text-sm font-bold text-white bg-primary shadow-lg shadow-primary/25 hover:bg-primary/90 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">check_circle</span>
                        Save Destination
                    </button>
                </div>
            </div>
        </div>

        <!-- Helper Card -->
        <div class="mt-8 p-4 bg-primary/5 rounded-xl border border-primary/10 flex items-start gap-3">
            <span class="material-symbols-outlined text-primary">tips_and_updates</span>
            <div>
                <p class="text-sm font-bold text-slate-900">Pro Tip</p>
                <p class="text-xs text-slate-600">Include high-quality landscape images to increase the engagement score by up to 40%. Destinations with ratings above 4.5 are automatically featured on the homepage.</p>
            </div>
        </div>
    </form>
</div>

<script>
    document.getElementById('image-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview').src = e.target.result;
                document.getElementById('image-preview-container').style.display = 'block';
                document.getElementById('image-placeholder').style.display = 'none';
            }
            reader.readAsDataURL(file);
        }
    });

    function clearImage() {
        document.getElementById('image-upload').value = '';
        document.getElementById('image-preview').src = '';
        document.getElementById('image-preview-container').style.display = 'none';
        document.getElementById('image-placeholder').style.display = 'flex';
    }
</script>
@endsection
