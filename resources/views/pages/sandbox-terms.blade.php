@extends('template.layouts.auth')

@section('title_postfix',  __('Terms of service'))

@section('content_header', __('Terms of service'))

@section('content')
<div class="bg-light min-vh-100 d-flex flex-row align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mb-4 mx-4">
                    <div class="card-body p-4">
                        <p>
                            This is not a production environment or a free service.
                            This is the demo and sandbox environment of YAFFA personal finance web application.
                            It can be reset or terminated any time, without prior notice.
                        </p>

                        <p>
                            <a href="https://www.yaffa.cc" target="_blank">Read more</a> about YAFFA personal finance web application in general,
                            or <a href="https://www.yaffa.cc/documentation/" target="_blank">read the documentation</a> to learn how to get your own instance of it.
                        </p>

                        <p>THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
                        IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
                        FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
                        AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
                        LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
                        OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
                        SOFTWARE.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop
