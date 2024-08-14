<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\FaqSection;
use Illuminate\Support\Str;
use App\Models\CategoryType;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\Admin\Language;
use App\Constants\LanguageConst;
use App\Models\Admin\SiteSections;
use App\Constants\SiteSectionConst;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;

class SetupSectionsController extends Controller
{
    protected $languages;

    public function __construct()
    {
        $this->languages = Language::whereNot('code',LanguageConst::NOT_REMOVABLE)->get();
    }

    /**
     * Register Sections with their slug
     * @param string $slug
     * @param string $type
     * @return string
     */
    public function section($slug,$type) {
        $sections = [
            'login-section'    => [
                'view'   => "loginView",
                'update' => "loginUpdate",
            ],
            'register-section'    => [
                'view'   => "registerView",
                'update' => "registerUpdate",
            ],
            'home_banner'    => [
                'view'   => "bannerView",
                'update' => "bannerUpdate",
            ],
            'about_section'  => [
                'view'       => "aboutView",
                'update'     => "aboutUpdate",
            ],
            'download-app'    => [
                'view'   => "downloadAppView",
                'update' => "downloadAppUpdate",
            ],
            'security-section'  => [
                'view'       => "securitySectionView",
                'update'     => "securitySectionUpdate",
                'itemStore'  => "securitySectionItemStore",
                'itemUpdate' => "securitySectionItemUpdate",
                'itemDelete' => "securitySectionItemDelete",
            ],
            'service-section'  => [
                'view'       => "serviceSectionView",
                'update'     => "serviceSectionUpdate",
                'itemStore'  => "serviceSectionItemStore",
                'itemUpdate' => "serviceSectionItemUpdate",
                'itemDelete' => "serviceSectionItemDelete",
            ],
            'testimonial-section'  => [
                'view'       => "testimonialView",
                'update'     => "testimonialUpdate",
                'itemStore'  => "testimonialItemStore",
                'itemUpdate' => "testimonialItemUpdate",
                'itemDelete' => "testimonialItemDelete",
            ],
            'how-it-work'  => [
                'view'       => "howItWorkView",
                'itemStore'  => "howItWorkItemStore",
                'itemUpdate' => "howItWorkItemUpdate",
                'itemDelete' => "howItWorkItemDelete",
            ],
            'contact'    => [
                'view'   => "contactView",
                'update' => "contactUpdate",
            ],
            'category'    => [
                'view' => "categoryView",
            ],
            'footer-section'  => [
                'view'       => "footerView",
                'update'     => "footerUpdate",
                'itemStore'  => "footerItemStore",
                'itemUpdate' => "footerItemUpdate",
                'itemDelete' => "footerItemDelete",
            ],
            'faq-section'    => [
                'view'       => "faqView",
                'update'     => "faqUpdate",
                'itemStore'  => "faqItemStore",
                'itemUpdate' => "faqItemUpdate",
                'itemDelete' => "faqItemDelete",
            ],

        ];

        if(!array_key_exists($slug,$sections)) abort(404);
        if(!isset($sections[$slug][$type])) abort(404);
        $next_step = $sections[$slug][$type];
        return $next_step;
    }



    /**
     * Method for getting specific step based on incomming request
     * @param string $slug
     * @return method
     */
    public function sectionView($slug) {
        $section = $this->section($slug,'view');
        return $this->$section($slug);
    }

    /**
     * Method for distribute store method for any section by using slug
     * @param string $slug
     * @param \Illuminate\Http\Request  $request
     * @return method
     */
    public function sectionItemStore(Request $request, $slug) {
        $section = $this->section($slug,'itemStore');
        return $this->$section($request,$slug);
    }

    /**
     * Method for distribute update method for any section by using slug
     * @param string $slug
     * @param \Illuminate\Http\Request  $request
     * @return method
     */
    public function sectionItemUpdate(Request $request, $slug) {
        $section = $this->section($slug,'itemUpdate');
        return $this->$section($request,$slug);
    }

    /**
     * Method for distribute delete method for any section by using slug
     * @param string $slug
     * @param \Illuminate\Http\Request  $request
     * @return method
     */
    public function sectionItemDelete(Request $request,$slug) {
        $section = $this->section($slug,'itemDelete');
        return $this->$section($request,$slug);
    }

    /**
     * Method for distribute update method for any section by using slug
     * @param string $slug
     * @param \Illuminate\Http\Request  $request
     * @return method
     */
    public function sectionUpdate(Request $request,$slug) {
        $section = $this->section($slug,'update');
        return $this->$section($request,$slug);
    }
    //========================LOGIN SECTION  Section Start============================
    public function loginView($slug) {
        $page_title = "Login Section";
        $section_slug = Str::slug(SiteSectionConst::LOGIN_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.login-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function loginUpdate(Request $request,$slug) {
        $basic_field_name = [
            'heading' => "required|string|max:100",
            'sub_heading' => "required|string",
        ];

        $slug = Str::slug(SiteSectionConst::LOGIN_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    //========================LOGIN SECTION  Section End============================
    //========================REGISTER SECTION  Section Start============================
    public function registerView($slug) {
        $page_title = "Register Section";
        $section_slug = Str::slug(SiteSectionConst::REGISTER_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.register-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function registerUpdate(Request $request,$slug) {
        $basic_field_name = [
            'heading' => "required|string|max:100",
            'sub_heading' => "required|string",
        ];

        $slug = Str::slug(SiteSectionConst::REGISTER_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        $validated['language']  = $this->contentValidate($request,$basic_field_name);

        $update_data['key']    = $slug;
        $update_data['value']  = $validated;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    //========================REGISTER SECTION  Section End============================

    /**
     * Mehtod for show banner section page
     * @param string $slug
     * @return view
     */
    public function bannerView($slug) {
        $page_title = "Home Banner Section";
        $section_slug = Str::slug(SiteSectionConst::HOME_BANNER);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.home-banner',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }

    /**
     * Mehtod for update banner section information
     * @param string $slug
     * @param \Illuminate\Http\Request  $request
     */
    public function bannerUpdate(Request $request,$slug) {

        $validator = Validator::make($request->all(),[
            'primary_button_link'      => 'required|string|max:255',
            'secondary_button_link'      => 'required|string|max:255',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validate();

        $basic_field_name = [
            'heading'     => "required|string|max:100",
            'sub_heading' => "required|string|max:450",
            'primary_button_name' => "required|string|max:50",
            'secondary_button_name' => "required|string|max:50",
            'title'       => "required|string|max:100",
        ];

        $slug = Str::slug(SiteSectionConst::HOME_BANNER);
        $section = SiteSections::where("key",$slug)->first();

        $data['images']['banner_image'] = $section->value->images->banner_image ?? "";
        $data['images']['image'] = $section->value->images->image ?? "";

        if($request->hasFile("banner_image")) {
            $data['images']['banner_image']  = $this->imageValidate($request,"banner_image",$section->value->images->banner_image ?? null);
        }



        $data['language']     = $this->contentValidate($request,$basic_field_name);
        $data['primary_button_link']   = $validated['primary_button_link'];
        $data['secondary_button_link']   = $validated['secondary_button_link'];
        $update_data['value'] = $data;
        $update_data['key']   = $slug;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }

    /**
     * Mehtod for show solutions section page
     * @param string $slug
     * @return view
     */
    public function aboutView($slug) {
        $page_title = "About Section";
        $section_slug = Str::slug(SiteSectionConst::ABOUT_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.about-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }

    /**
     * Mehtod for update solutions section information
     * @param string $slug
     * @param \Illuminate\Http\Request  $request
     */
    public function aboutUpdate(Request $request,$slug) {

        $validator = Validator::make($request->all(), [
            'total_user'        => 'required',
            'total_transaction' => 'required',
            'total_gateway'     => 'required',
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $basic_field_name = [
            'heading' => "required|string|max:120",
            'details' => "required|string|max:1000",
        ];

        $slug = Str::slug(SiteSectionConst::ABOUT_SECTION);
        $section = SiteSections::where("key",$slug)->first();
        if($section != null) {
            $data = json_decode(json_encode($section->value),true);
        }else {
            $data = [];
        }
        $data['images']['image_one'] = $section->value->images->image_one ?? "";

        if($request->hasFile("image_one")) {
            $data['images']['image_one']      = $this->imageValidate($request,"image_one",$section->value->images->image_one ?? null);
        }

        $data['images']['image_two'] = $section->value->images->image_two ?? "";
        if($request->hasFile("image_two")) {
            $data['images']['image_two']      = $this->imageValidate($request,"image_two",$section->value->images->image_two ?? null);
        }

        $data['images']['image_three'] = $section->value->images->image_three ?? "";
        if($request->hasFile("image_three")) {
            $data['images']['image_three']      = $this->imageValidate($request,"image_three",$section->value->images->image_three ?? null);
        }

        $data['images']['image_four'] = $section->value->images->image_four ?? "";
        if($request->hasFile("image_four")) {
            $data['images']['image_four']      = $this->imageValidate($request,"image_four",$section->value->images->image_four ?? null);
        }

        $data['images']['image_five'] = $section->value->images->image_five ?? "";
        if($request->hasFile("image_five")) {
            $data['images']['image_five']      = $this->imageValidate($request,"image_five",$section->value->images->image_five ?? null);
        }

        $data['images']['image_six'] = $section->value->images->image_six ?? "";
        if($request->hasFile("image_six")) {
            $data['images']['image_six']      = $this->imageValidate($request,"image_six",$section->value->images->image_six ?? null);
        }

        $data['language']          = $this->contentValidate($request,$basic_field_name);
        $data['total_user']        = $validated['total_user'];
        $data['total_transaction'] = $validated['total_transaction'];
        $data['total_gateway']     = $validated['total_gateway'];

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    //=======================Download App Section Start============================
    public function downloadAppView($slug) {
        $page_title = "Download App Section";
        $section_slug = Str::slug(SiteSectionConst::DOWNLOAD_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.download-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function downloadAppUpdate(Request $request,$slug) {

        $basic_field_name = [
            'title'           => "required|string|max:100",
            'heading'         => "required|string|max:100",
            'sub_heading'     => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::DOWNLOAD_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        $data['language']  = $this->contentValidate($request,$basic_field_name);


        $data['images']['image'] = $section->value->images->image ?? "";
        $data['images']['play_store_image'] = $section->value->images->play_store_image ?? "";
        $data['images']['app_store_image'] = $section->value->images->app_store_image ?? "";

        if($request->hasFile("image")) {
            $data['images']['image']  = $this->imageValidate($request,"image",$section->value->images->image ?? null);
        }
        if($request->hasFile("play_store_image")) {
            $data['images']['play_store_image']  = $this->imageValidate($request,"play_store_image",$section->value->images->play_store_image ?? null);
        }
        if($request->hasFile("app_store_image")) {
            $data['images']['app_store_image']  = $this->imageValidate($request,"app_store_image",$section->value->images->app_store_image ?? null);
        }

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    //=======================Download App Section End=========================


    //=======================testimonial Section End===============================
    public function howItWorkView($slug) {
        $page_title = __('How It Work');
        $section_slug = Str::slug(SiteSectionConst::HOW_IT_WORK);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.how-it-work',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function howItWorkItemStore(Request $request,$slug) {

        $basic_field_name = [
            'title'        => "required|string|max:100",
            'designation' => "required|string|max:100",
            'details'     => "required|string|max:450",
        ];

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"how-it-work-add");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;
        $slug = Str::slug(SiteSectionConst::HOW_IT_WORK);
        $section = SiteSections::where("key",$slug)->first();

        if($section != null) {
            $section_data = json_decode(json_encode($section->value),true);
        }else {
            $section_data = [];
        }
        $unique_id = uniqid();

        $section_data['items'][$unique_id]['language'] = $language_wise_data;
        $section_data['items'][$unique_id]['id']       = $unique_id;
        $section_data['items'][$unique_id]['image']    = "";

        if($request->hasFile("image")) {
            $section_data['items'][$unique_id]['image'] = $this->imageValidate($request,"image",$section->value->items->image ?? null);
        }

        $update_data['key'] = $slug;
        $update_data['value']   = $section_data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Item Added Successfully!')]]);
    }

    public function howItWorkItemUpdate(Request $request,$slug) {


        $validator = Validator::make($request->all(), [
            'target'             => "required|string",
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput()->with('modal', 'how-it-work-edit');
        }

        $validated = $validator->validated();

        $basic_field_name = [
            'title_edit'        => "required|string|max:100",
            'details_edit'     => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::HOW_IT_WORK);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        $request->merge(['old_image' => $section_values['items'][$request->target]['image'] ?? null]);

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"testimonial-edit");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;

        $language_wise_data = array_map(function($language) {
            return replace_array_key($language,"_edit");
        },$language_wise_data);



        $section_values['items'][$request->target]['language'] = $language_wise_data;

        if($request->hasFile("image")) {
            $section_values['items'][$request->target]['image']    = $this->imageValidate($request,"image",$section_values['items'][$request->target]['image'] ?? null);
        }

        try{
            $section->update([
                'value' => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Information Updated Successfully!')]]);
    }

    public function howItWorkItemDelete(Request $request,$slug) {
        $request->validate([
            'target'    => 'required|string',
        ]);
        $slug = Str::slug(SiteSectionConst::HOW_IT_WORK);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        try{
            $image_link = get_files_path('site-section') . '/' . $section_values['items'][$request->target]['image'];
            unset($section_values['items'][$request->target]);
            delete_file($image_link);
            $section->update([
                'value'     => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section item delete successfully!')]]);
    }
    //=======================testimonial Section End===============================

    //=======================testimonial Section End===============================
    public function testimonialView($slug) {
        $page_title = "Testimonial Section";
        $section_slug = Str::slug(SiteSectionConst::TESTIMONIAL_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.testimonial-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function testimonialUpdate(Request $request,$slug) {

        $basic_field_name = [
            'heading'      => "required|string|max:100",
            'sub_heading'  => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::TESTIMONIAL_SECTION);
        $section = SiteSections::where("key",$slug)->first();
        if($section != null) {
            $data = json_decode(json_encode($section->value),true);
        }else {
            $data = [];
        }

        $data['images']['image'] = $section->value->images->image ?? "";
        if($request->hasFile("image")) {
            $data['images']['image']      = $this->imageValidate($request,"image",$section->value->images->image ?? null);
        }

        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    public function testimonialItemStore(Request $request,$slug) {

        $validator = Validator::make($request->all(), [
            'review_rating'       => "required|numeric|max:5",
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput()->with('modal', 'testimonial-add');
        }

        $validated = $validator->validated();

        $basic_field_name = [
            'name'        => "required|string|max:100",
            'title'        => "required|string|max:100",
            'designation' => "required|string|max:100",
            'details'     => "required|string|max:450",
        ];

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"testimonial-add");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;
        $slug = Str::slug(SiteSectionConst::TESTIMONIAL_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        if($section != null) {
            $section_data = json_decode(json_encode($section->value),true);
        }else {
            $section_data = [];
        }
        $unique_id = uniqid();

        $section_data['items'][$unique_id]['language'] = $language_wise_data;
        $section_data['items'][$unique_id]['id'] = $unique_id;
        $section_data['items'][$unique_id]['review_rating'] = $validated['review_rating'];
        $section_data['items'][$unique_id]['image'] = "";

        if($request->hasFile("image")) {
            $section_data['items'][$unique_id]['image'] = $this->imageValidate($request,"image",$section->value->items->image ?? null);
        }

        $update_data['key'] = $slug;
        $update_data['value']   = $section_data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Item Added Successfully!')]]);
    }
    public function testimonialItemUpdate(Request $request,$slug) {


        $validator = Validator::make($request->all(), [
            'review_rating_edit' => "required|integer|max:5",
            'target'             => "required|string",
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput()->with('modal', 'testimonial-edit');
        }

        $validated = $validator->validated();

        $basic_field_name = [
            'name_edit'        => "required|string|max:100",
            'designation_edit' => "required|string|max:100",
            'title_edit' => "required|string|max:100",
            'details_edit'     => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::TESTIMONIAL_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        $request->merge(['old_image' => $section_values['items'][$request->target]['image'] ?? null]);

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"testimonial-edit");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;

        $language_wise_data = array_map(function($language) {
            return replace_array_key($language,"_edit");
        },$language_wise_data);



        $section_values['items'][$request->target]['language'] = $language_wise_data;
        $section_values['items'][$request->target]['review_rating'] = $validated['review_rating_edit'];

        if($request->hasFile("image")) {
            $section_values['items'][$request->target]['image']    = $this->imageValidate($request,"image",$section_values['items'][$request->target]['image'] ?? null);
        }

        try{
            $section->update([
                'value' => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Information Updated Successfully!')]]);
    }

    public function testimonialItemDelete(Request $request,$slug) {
        $request->validate([
            'target'    => 'required|string',
        ]);
        $slug = Str::slug(SiteSectionConst::TESTIMONIAL_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        try{
            $image_link = get_files_path('site-section') . '/' . $section_values['items'][$request->target]['image'];
            unset($section_values['items'][$request->target]);
            delete_file($image_link);
            $section->update([
                'value'     => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section item delete successfully!')]]);
    }
//=======================testimonial Section End===============================


        //=======================Category  Section Start=======================
        public function categoryView(){
            $page_title = __("Setup Category Type");
            $allCategory = CategoryType::orderByDesc('id')->paginate(10);
            return view('admin.sections.categoryType.index',compact(
                'page_title',
                'allCategory',
            ));
        }
        public function storeCategory(Request $request){
            $validator = Validator::make($request->all(),[
                'name'      => 'required|string',
                'type'   => 'required',
            ]);
            if($validator->fails()) {
                return back()->withErrors($validator)->withInput()->with('modal','category-add');
            }
            $validated = $validator->validate();
            $slugData = "faq-".Str::slug($request->name);
            $makeUnique = CategoryType::where('slug',  $slugData)->first();
            if($makeUnique){
                return back()->with(['error' => [ $request->name.' '.__('Category Already Exists!')]]);
            }


            $validated['name']          = $request->name;
            $validated['slug']          = $slugData;
            $validated['type']           = $request->type;
            try{
                CategoryType::create($validated);
                return back()->with(['success' => [__('Category Saved Successfully!')]]);
            }catch(Exception $e) {
                return back()->withErrors($validator)->withInput()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
            }
        }
        public function categoryUpdate(Request $request){
            $target = $request->target;
            $category = CategoryType::where('id',$target)->first();
            $validator = Validator::make($request->all(),[
                'name'      => 'required|string',
                'type'   => 'required',
            ]);
            if($validator->fails()) {
                return back()->withErrors($validator)->withInput()->with('modal','edit-category');
            }
            $validated = $validator->validate();
            if($request->type == 1){
                $slugData = "faq-".Str::slug($request->name);
            }elseif($request->type == 2){
                $slugData = "event-".Str::slug($request->name);
            }
            $makeUnique = CategoryType::where('id',"!=",$category->id)->where('slug',  $slugData)->first();
            if($makeUnique){
                return back()->with(['error' => [ $request->name.' '.'Category Already Exists!']]);
            }
            $validated['name']          = $request->name;
            $validated['slug']          = $slugData;
            $validated['type']       = $request->type;
            try{
                $category->fill($validated)->save();
                return back()->with(['success' => [__('Category Updated Successfully!')]]);
            }catch(Exception $e) {
                return back()->withErrors($validator)->withInput()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
            }
        }
        public function categoryStatusUpdate(Request $request) {
            $validator = Validator::make($request->all(),[
                'status'                    => 'required|boolean',
                'data_target'               => 'required|string',
            ]);
            if ($validator->stopOnFirstFailure()->fails()) {
                $error = ['error' => $validator->errors()];
                return Response::error($error,null,400);
            }
            $validated = $validator->safe()->all();
            $category_id = $validated['data_target'];

            $category = CategoryType::where('id',$category_id)->first();
            if(!$category) {
                $error = ['error' => [__('Category record not found in our system.')]];
                return Response::error($error,null,404);
            }

            try{
                $category->update([
                    'status' => ($validated['status'] == true) ? false : true,
                ]);
            }catch(Exception $e) {
                $error = ['error' => [__('Something Went Wrong! Please Try Again.')]];
                return Response::error($error,null,500);
            }

            $success = ['success' => [__('Category status updated successfully!')]];
            return Response::success($success,null,200);
        }
        public function categoryDelete(Request $request) {
            $validator = Validator::make($request->all(),[
                'target'        => 'required|string|exists:category_types,id',
            ]);
            $validated = $validator->validate();
            $category = CategoryType::where("id",$validated['target'])->first();
            if($category->type == 1){
                $type = "FAQ"??"";

            }else{
                $type = "Event"??"";
            }

            try{
                $category->delete();
            }catch(Exception $e) {
                return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
            }

            return back()->with(['success' => [ $type.' '.__('Category deleted successfully!')]]);
        }
    //=======================Category  Section End=======================

    //=======================How it work Section Start===============================
    public function securitySectionView($slug) {
        $page_title = __('Security Section');
        $section_slug = Str::slug(SiteSectionConst::SECURITY_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.security-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function securitySectionUpdate(Request $request,$slug) {
        $basic_field_name = [
            'heading' => "required|string|max:100",
            'sub_heading' => "required|string|max:100",
        ];

        $slug = Str::slug(SiteSectionConst::SECURITY_SECTION);
        $section = SiteSections::where("key",$slug)->first();
        if($section != null) {
            $data = json_decode(json_encode($section->value),true);
        }else {
            $data = [];
        }

        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    public function securitySectionItemStore(Request $request,$slug) {

        $validator = Validator::make($request->all(), [
            'icon' => 'required|string'
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','security-section-add');
        }

        $validated = $validator->validate();

        $basic_field_name = [
            'name'     => "required|string|max:100",
            'details'   => "required|string|max:450",
        ];

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"security-section-add");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;
        $slug = Str::slug(SiteSectionConst::SECURITY_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        if($section != null) {
            $section_data = json_decode(json_encode($section->value),true);
        }else {
            $section_data = [];
        }
        $unique_id = uniqid();
        $section_data['items'][$unique_id]['language'] = $language_wise_data;
        $section_data['items'][$unique_id]['id'] = $unique_id;
        $section_data['items'][$unique_id]['icon'] = $validated['icon'];

        $update_data['key'] = $slug;
        $update_data['value']   = $section_data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Item Added Successfully!')]]);
    }
    public function securitySectionItemUpdate(Request $request,$slug) {

        $validator = Validator::make($request->all(), [
            'icon_edit' => 'required|string'
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','security-section-edit');
        }

        $validated = $validator->validate();


        $request->validate([
            'target'    => "required|string",
        ]);

        $basic_field_name = [
            'name_edit'     => "required|string|max:100",
            'details_edit'   => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::SECURITY_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);

        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"security-section-edit");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;

        $language_wise_data = array_map(function($language) {
            return replace_array_key($language,"_edit");
        },$language_wise_data);


        $section_values['items'][$request->target]['language'] = $language_wise_data;
        $section_values['items'][$request->target]['icon'] = $validated['icon_edit'];

        try{
            $section->update([
                'value' => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Information Updated Successfully!')]]);
    }
    public function securitySectionItemDelete(Request $request,$slug) {
        $request->validate([
            'target'    => 'required|string',
        ]);
        $slug = Str::slug(SiteSectionConst::SECURITY_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        try{
            unset($section_values['items'][$request->target]);
            $section->update([
                'value'     => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section item delete successfully!')]]);
    }
    //=======================How it work Section End===============================


    //=======================Service Section Start===============================
    public function serviceSectionView($slug) {
        $page_title = "Service Section";
        $section_slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.service-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function serviceSectionUpdate(Request $request,$slug) {
        $basic_field_name = [
            'heading' => "required|string|max:100",
            'sub_heading' => "required|string|max:450"
        ];

        $slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $section = SiteSections::where("key",$slug)->first();
        if($section != null) {
            $data = json_decode(json_encode($section->value),true);
        }else {
            $data = [];
        }

        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    public function serviceSectionItemStore(Request $request,$slug) {

        $validator = Validator::make($request->all(), [
            'icon' => 'required|string'
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','service-section-add');
        }

        $validated = $validator->validate();

        $basic_field_name = [
            'name'     => "required|string|max:100",
            'details'   => "required|string|max:450",
        ];

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"service-section-add");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;
        $slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        if($section != null) {
            $section_data = json_decode(json_encode($section->value),true);
        }else {
            $section_data = [];
        }
        $unique_id = uniqid();
        $section_data['items'][$unique_id]['language'] = $language_wise_data;
        $section_data['items'][$unique_id]['id'] = $unique_id;
        $section_data['items'][$unique_id]['icon'] = $validated['icon'];

        $update_data['key'] = $slug;
        $update_data['value']   = $section_data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Item Added Successfully!')]]);
    }
    public function serviceSectionItemUpdate(Request $request,$slug) {

        $validator = Validator::make($request->all(), [
            'icon_edit' => 'required|string'
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal','service-section-edit');
        }

        $validated = $validator->validate();


        $request->validate([
            'target'    => "required|string",
        ]);

        $basic_field_name = [
            'name_edit'     => "required|string|max:100",
            'details_edit'   => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);

        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"service-section-edit");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;

        $language_wise_data = array_map(function($language) {
            return replace_array_key($language,"_edit");
        },$language_wise_data);


        $section_values['items'][$request->target]['language'] = $language_wise_data;
        $section_values['items'][$request->target]['icon'] = $validated['icon_edit'];

        try{
            $section->update([
                'value' => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Information Updated Successfully!')]]);
    }
    public function serviceSectionItemDelete(Request $request,$slug) {
        $request->validate([
            'target'    => 'required|string',
        ]);
        $slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        try{
            unset($section_values['items'][$request->target]);
            $section->update([
                'value'     => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section item delete successfully!')]]);
    }
    //=======================Service Section End===============================

    //======================= work Section Start===============================

    public function contactView($slug) {
        $page_title = "Contact Section";
        $section_slug = Str::slug(SiteSectionConst::CONTACT_SECTION);
        $data = SiteSections::getData($section_slug)->first();
        $languages = $this->languages;

        return view('admin.sections.setup-sections.contact-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function contactUpdate(Request $request,$slug) {


        $validator = Validator::make($request->all(), [
            'embed_map' => 'required|string'
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validate();

        $basic_field_name = [
            'title'       => "required|string|max:100",
            'heading'     => "required|string|max:100",
            'heading_two'     => "required|string|max:100",
            'sub_heading' => "required|string|max:450",
            'sub_heading_two' => "required|string|max:450",
            'address'     => "required|string|max:450",
            'phone'       => "required|string",
            'email'       => "required|string|max:100",
        ];

        $slug = Str::slug(SiteSectionConst::CONTACT_SECTION);
        $section = SiteSections::where("key",$slug)->first();
        $data['language']  = $this->contentValidate($request,$basic_field_name);
        $data['embed_map'] = $validated['embed_map'];
        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    //=======================Download App Section End==============================

      //=======================footer Section End===============================

    public function  footerView($slug) {
        $page_title = "Footer Section";
        $section_slug = Str::slug(SiteSectionConst::FOOTER_SECTION);
        $data = SiteSections::getData($section_slug)->first();

        $languages = $this->languages;

        return view('admin.sections.setup-sections.footer-section',compact(
            'page_title',
            'data',
            'languages',
            'slug',
        ));
    }
    public function  footerUpdate(Request $request,$slug) {
        $basic_field_name = [
            'footer_text' => "required|string|max:100",
            'newsltter_details' => "required|string|max:450",
            'about_details' => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::FOOTER_SECTION);
        $section = SiteSections::where("key",$slug)->first();
        if($section != null) {
            $data = json_decode(json_encode($section->value),true);
        }else {
            $data = [];
        }
        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }
    public function  footerItemStore(Request $request,$slug) {
        $basic_field_name = [
            'name'     => "required|string|max:100",
            'social_icon'   => "required|string|max:450",
            'link'   => "required|string|url|max:450",
        ];

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"icon-add");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;
        $slug = Str::slug(SiteSectionConst::FOOTER_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        if($section != null) {
            $section_data = json_decode(json_encode($section->value),true);
        }else {
            $section_data = [];
        }
        $unique_id = uniqid();

        $section_data['items'][$unique_id]['language'] = $language_wise_data;
        $section_data['items'][$unique_id]['id'] = $unique_id;

        $update_data['key'] = $slug;
        $update_data['value']   = $section_data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Item Added Successfully!')]]);
    }
    public function  footerItemUpdate(Request $request,$slug) {

        $request->validate([
            'target'    => "required|string",
        ]);

        $basic_field_name = [
            'name_edit'     => "required|string|max:100",
            'social_icon_edit'   => "required|string|max:450",
            'link_edit'   => "required|string|url|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::FOOTER_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"icon-edit");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;

        $language_wise_data = array_map(function($language) {
            return replace_array_key($language,"_edit");
        },$language_wise_data);

        $section_values['items'][$request->target]['language'] = $language_wise_data;
        try{
            $section->update([
                'value' => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Information Updated Successfully!')]]);
    }

    public function footerItemDelete(Request $request,$slug) {
        $request->validate([
            'target'    => 'required|string',
        ]);
        $slug = Str::slug(SiteSectionConst::FOOTER_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        try{
            unset($section_values['items'][$request->target]);
            $section->update([
                'value'     => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section item delete successfully!')]]);
    }

    //=======================footer Section End===============================

    //=======================Faq  Section Start=======================
    public function faqView($slug){
        $page_title = "Setup FAQ";
        $allFaq = FaqSection::orderByDesc('id')->get();

        $section_slug = Str::slug(SiteSectionConst::FAQ_SECTION);
        $data = SiteSections::getData($section_slug)->first();

        $languages = $this->languages;

        return view('admin.sections.faq.index',compact(
            'page_title',
            'allFaq',
            'slug',
            'languages',
            'data',
        ));
    }
    public function  faqUpdate(Request $request,$slug) {
        $basic_field_name = [
            'heading'      => "required|string|max:100",
            'sub_heading'  => "required|string|max:450",
        ];
        $slug = Str::slug(SiteSectionConst::FAQ_SECTION);
        $section = SiteSections::where("key",$slug)->first();
        if($section != null) {
            $data = json_decode(json_encode($section->value),true);
        }else {
            $data = [];
        }
        $data['language']  = $this->contentValidate($request,$basic_field_name);

        $update_data['key']    = $slug;
        $update_data['value']  = $data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Updated Successfully!')]]);
    }

    public function faqItemStore(Request $request,$slug) {

        $basic_field_name = [
            'question' => "required|string|max:100",
            'answer'   => "required|string|max:400",
        ];

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"faq-add");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;
        $slug = Str::slug(SiteSectionConst::FAQ_SECTION);
        $section = SiteSections::where("key",$slug)->first();

        if($section != null) {
            $section_data = json_decode(json_encode($section->value),true);
        }else {
            $section_data = [];
        }
        $unique_id = uniqid();
        $section_data['items'][$unique_id]['language'] = $language_wise_data;
        $section_data['items'][$unique_id]['id'] = $unique_id;

        $update_data['key'] = $slug;
        $update_data['value']   = $section_data;

        try{
            SiteSections::updateOrCreate(['key' => $slug],$update_data);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section Item Added Successfully!')]]);
    }
    public function faqItemUpdate(Request $request,$slug) {

        $request->validate([
            'target'    => "required|string",
        ]);

        $basic_field_name = [
            'question_edit'     => "required|string|max:100",
            'answer_edit'   => "required|string|max:450",
        ];

        $slug = Str::slug(SiteSectionConst::FAQ_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);

        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);

        $language_wise_data = $this->contentValidate($request,$basic_field_name,"faq-edit");
        if($language_wise_data instanceof RedirectResponse) return $language_wise_data;

        $language_wise_data = array_map(function($language) {
            return replace_array_key($language,"_edit");
        },$language_wise_data);


        $section_values['items'][$request->target]['language'] = $language_wise_data;

        try{
            $section->update([
                'value' => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Information Updated Successfully!')]]);
    }
    public function faqItemDelete(Request $request,$slug) {
        $request->validate([
            'target'    => 'required|string',
        ]);
        $slug = Str::slug(SiteSectionConst::FAQ_SECTION);
        $section = SiteSections::getData($slug)->first();
        if(!$section) return back()->with(['error' => [__('Section not found!')]]);
        $section_values = json_decode(json_encode($section->value),true);
        if(!isset($section_values['items'])) return back()->with(['error' => [__('Section item not found!')]]);
        if(!array_key_exists($request->target,$section_values['items'])) return back()->with(['error' => [__('Section item is invalid!')]]);
        try{
            unset($section_values['items'][$request->target]);
            $section->update([
                'value'     => $section_values,
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Section item delete successfully!')]]);
    }


//=======================Faq Section End=======================


    /**
     * Method for get languages form record with little modification for using only this class
     * @return array $languages
     */
    public function languages() {
        $languages = Language::whereNot('code',LanguageConst::NOT_REMOVABLE)->select("code","name")->get()->toArray();
        $languages[] = [
            'name'      => LanguageConst::NOT_REMOVABLE_CODE,
            'code'      => LanguageConst::NOT_REMOVABLE,
        ];
        return $languages;
    }

    /**
     * Method for validate request data and re-decorate language wise data
     * @param object $request
     * @param array $basic_field_name
     * @return array $language_wise_data
     */
    public function contentValidate($request,$basic_field_name,$modal = null) {
        $languages = $this->languages();

        $current_local = get_default_language_code();
        $validation_rules = [];
        $language_wise_data = [];
        foreach($request->all() as $input_name => $input_value) {
            foreach($languages as $language) {
                $input_name_check = explode("_",$input_name);
                $input_lang_code = array_shift($input_name_check);
                $input_name_check = implode("_",$input_name_check);
                if($input_lang_code == $language['code']) {
                    if(array_key_exists($input_name_check,$basic_field_name)) {
                        $langCode = $language['code'];
                        if($current_local == $langCode) {
                            $validation_rules[$input_name] = $basic_field_name[$input_name_check];
                        }else {
                            $validation_rules[$input_name] = str_replace("required","nullable",$basic_field_name[$input_name_check]);
                        }
                        $language_wise_data[$langCode][$input_name_check] = $input_value;
                    }
                    break;
                }
            }
        }
        if($modal == null) {
            $validated = Validator::make($request->all(),$validation_rules)->validate();
        }else {
            $validator = Validator::make($request->all(),$validation_rules);
            if($validator->fails()) {
                return back()->withErrors($validator)->withInput()->with("modal",$modal);
            }
            $validated = $validator->validate();
        }

        return $language_wise_data;
    }

    /**
     * Method for validate request image if have
     * @param object $request
     * @param string $input_name
     * @param string $old_image
     * @return boolean|string $upload
     */
    public function imageValidate($request,$input_name,$old_image) {
        if($request->hasFile($input_name)) {
            $image_validated = Validator::make($request->only($input_name),[
                $input_name         => "image|mimes:png,jpg,webp,jpeg,svg",
            ])->validate();
            $image = get_files_from_fileholder($request,$input_name);
            $upload = upload_files_from_path_dynamic($image,'site-section',$old_image);
            return $upload;
        }

        return false;
    }

}
